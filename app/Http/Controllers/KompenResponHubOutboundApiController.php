<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowKompenResponHubOutboundStudentRequest;
use App\Http\Requests\StoreKompenResponHubApiSyncRunRequest;
use App\Http\Resources\KompenResponHubOutboundStudentResource;
use App\Jobs\GenerateKompenResponHubApiSyncArtifact;
use App\KompenResponHub\SikompenApiClientConfiguration;
use App\Models\KompenResponHubApiClient;
use App\Models\KompenResponHubApiSyncRun;
use App\Models\KompenResponHubStudent;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class KompenResponHubOutboundApiController extends Controller
{
    private const CONFIGURED_RUNTIME_CLIENT_ID = 'configured-runtime';

    public function student(ShowKompenResponHubOutboundStudentRequest $request, string $nim): Response
    {
        $validated = $request->validated();
        $student = $this->studentQuery()
            ->where('nim', $nim)
            ->where('periode_semester', $validated['periode'])
            ->orderByDesc('id')
            ->first();

        if (! $student instanceof KompenResponHubStudent) {
            return $this->error($request, 'not_found', 'Data mahasiswa tidak ditemukan untuk periode yang diminta.', Response::HTTP_NOT_FOUND);
        }

        return $this->success($request, [
            'data' => (new KompenResponHubOutboundStudentResource($student))->resolve($request),
        ]);
    }

    public function storeSyncRun(StoreKompenResponHubApiSyncRunRequest $request): Response
    {
        $client = $this->client($request);
        $validated = $request->validated();
        /** @var array{type: 'kelas'|'periode', periode: string, kelas?: string|null} $selector */
        $selector = $validated['selector'];
        $lockKey = 'sikompen:api-sync:'.hash('sha256', implode('|', [
            $client->key,
            $selector['type'],
            $selector['periode'],
            $selector['kelas'] ?? '',
        ]));

        try {
            return Cache::lock($lockKey, 10)->block(5, function () use ($request, $client, $selector): Response {
                $existingRun = $this->findReusableRun($client, $selector);

                if ($existingRun instanceof KompenResponHubApiSyncRun) {
                    return $this->syncRunResponse($request, $existingRun, Response::HTTP_OK);
                }

                $query = KompenResponHubStudent::query()
                    ->where('periode_semester', $selector['periode']);

                if ($selector['type'] === 'kelas') {
                    $query->where('kelas', $selector['kelas'] ?? null);
                }

                if (! $query->exists()) {
                    return $this->error($request, 'not_found', 'Tidak ada data Kompen dan Respon untuk selector yang diminta.', Response::HTTP_NOT_FOUND);
                }

                $run = KompenResponHubApiSyncRun::query()->create([
                    'api_client_id' => $this->configuredRuntimeClientId(),
                    'api_client_key' => $client->key,
                    'run_id' => (string) Str::ulid(),
                    'idempotency_key' => 'internal:'.$client->key.':'.Str::ulid(),
                    'selector_type' => $selector['type'],
                    'periode_semester' => $selector['periode'],
                    'kelas' => $selector['type'] === 'kelas' ? $selector['kelas'] : null,
                    'status' => 'queued',
                ]);

                GenerateKompenResponHubApiSyncArtifact::dispatch($run->id)
                    ->onQueue(config('kompen-respon-hub.api_sync.queue'));

                return $this->syncRunResponse($request, $run, Response::HTTP_ACCEPTED);
            });
        } catch (LockTimeoutException) {
            return $this->error($request, 'sync_request_busy', 'Permintaan sinkronisasi yang sama sedang diproses. Coba lagi beberapa detik.', Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => '3',
            ]);
        }
    }

    public function showSyncRun(Request $request, string $runId): Response
    {
        $run = $this->findOwnedRun($request, $runId);

        if (! $run instanceof KompenResponHubApiSyncRun) {
            return $this->error($request, 'not_found', 'Sync run tidak ditemukan.', Response::HTTP_NOT_FOUND);
        }

        if ($run->status === 'expired' || ($run->expires_at !== null && $run->expires_at->isPast())) {
            return $this->error($request, 'artifact_expired', 'Artifact sync telah kedaluwarsa.', Response::HTTP_GONE);
        }

        return $this->success($request, [
            'data' => $this->syncRunData($run),
        ]);
    }

    public function downloadSyncRun(Request $request, string $runId): BinaryFileResponse|Response
    {
        $run = $this->findOwnedRun($request, $runId);

        if (! $run instanceof KompenResponHubApiSyncRun) {
            return $this->error($request, 'not_found', 'Sync run tidak ditemukan.', Response::HTTP_NOT_FOUND);
        }

        if ($run->status === 'expired' || ($run->expires_at !== null && $run->expires_at->isPast())) {
            return $this->error($request, 'artifact_expired', 'Artifact sync telah kedaluwarsa.', Response::HTTP_GONE);
        }

        if ($run->status !== 'ready' || $run->artifact_path === null) {
            return $this->error($request, 'artifact_not_ready', 'Artifact belum siap diunduh.', Response::HTTP_CONFLICT);
        }

        if (! Storage::disk('local')->exists($run->artifact_path)) {
            return $this->error($request, 'artifact_expired', 'Artifact sync tidak lagi tersedia.', Response::HTTP_GONE);
        }

        return response()->download(
            Storage::disk('local')->path($run->artifact_path),
            "kompen-respon-{$run->run_id}.ndjson.gz",
            [
                'Cache-Control' => 'no-store',
                'Content-Type' => 'application/gzip',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /** @return Builder<KompenResponHubStudent> */
    private function studentQuery(): Builder
    {
        return KompenResponHubStudent::query()->with(['progress', 'summaryOverride']);
    }

    /**
     * @param  array{type: 'kelas'|'periode', periode: string, kelas?: string|null}  $selector
     */
    private function findReusableRun(SikompenApiClientConfiguration $client, array $selector): ?KompenResponHubApiSyncRun
    {
        return KompenResponHubApiSyncRun::query()
            ->where('api_client_key', $client->key)
            ->where('selector_type', $selector['type'])
            ->where('periode_semester', $selector['periode'])
            ->where('kelas', $selector['type'] === 'kelas' ? $selector['kelas'] ?? null : null)
            ->where(function (Builder $query): void {
                $query->whereIn('status', ['queued', 'processing'])
                    ->orWhere(function (Builder $readyQuery): void {
                        $readyQuery
                            ->where('status', 'ready')
                            ->where('expires_at', '>', now());
                    });
            })
            ->latest('id')
            ->first();
    }

    private function client(Request $request): SikompenApiClientConfiguration
    {
        $client = $request->attributes->get('sikompen_api_client');

        abort_unless($client instanceof SikompenApiClientConfiguration, Response::HTTP_UNAUTHORIZED);

        return $client;
    }

    private function configuredRuntimeClientId(): int
    {
        $id = KompenResponHubApiClient::query()
            ->where('client_id', self::CONFIGURED_RUNTIME_CLIENT_ID)
            ->value('id');

        abort_unless(is_int($id), Response::HTTP_SERVICE_UNAVAILABLE);

        return $id;
    }

    private function findOwnedRun(Request $request, string $runId): ?KompenResponHubApiSyncRun
    {
        return KompenResponHubApiSyncRun::query()
            ->where('api_client_key', $this->client($request)->key)
            ->where('run_id', $runId)
            ->first();
    }

    private function syncRunResponse(Request $request, KompenResponHubApiSyncRun $run, int $status): Response
    {
        return $this->success($request, [
            'data' => $this->syncRunData($run),
        ], $status);
    }

    /** @return array<string, mixed> */
    private function syncRunData(KompenResponHubApiSyncRun $run): array
    {
        return [
            'sync_run_id' => $run->run_id,
            'status' => $run->status,
            'selector' => [
                'type' => $run->selector_type,
                'periode' => $run->periode_semester,
                'kelas' => $run->kelas,
            ],
            'snapshot_at' => $run->snapshot_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
            'record_count' => $run->record_count,
            'artifact' => [
                'format' => 'ndjson',
                'compression' => 'gzip',
                'sha256' => $run->artifact_checksum,
                'size_bytes' => $run->artifact_size_bytes,
            ],
            'download_url' => $run->status === 'ready' ? route('api.sikompen.v1.sync-runs.download', $run->run_id) : null,
            'expires_at' => $run->expires_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
            'failure_code' => $run->failure_code,
        ];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function error(Request $request, string $code, string $message, int $status, array $headers = []): Response
    {
        return response()->json([
            'status' => 'error',
            'request_id' => $this->requestId($request),
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status, [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            ...$headers,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function success(Request $request, array $data, int $status = Response::HTTP_OK): Response
    {
        return response()->json([
            'status' => 'success',
            'request_id' => $this->requestId($request),
            ...$data,
        ], $status, [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->attributes->get('sikompen_api_request_id');

        return is_string($requestId) ? $requestId : (string) Str::ulid();
    }
}
