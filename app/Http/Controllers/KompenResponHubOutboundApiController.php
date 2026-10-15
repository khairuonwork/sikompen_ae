<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowKompenResponHubOutboundStudentRequest;
use App\Http\Requests\StoreKompenResponHubApiSyncRunRequest;
use App\Http\Resources\KompenResponHubOutboundStudentResource;
use App\Jobs\GenerateKompenResponHubApiSyncArtifact;
use App\Models\KompenResponHubApiClient;
use App\Models\KompenResponHubApiSyncRun;
use App\Models\KompenResponHubStudent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class KompenResponHubOutboundApiController extends Controller
{
    public function student(ShowKompenResponHubOutboundStudentRequest $request, string $nim): Response
    {
        $validated = $request->validated();
        $student = $this->studentQuery()
            ->where('nim', $nim)
            ->where('periode_semester', $validated['periode'])
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
        $selector = $validated['selector'];
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! is_string($idempotencyKey) || ! preg_match('/^[\x21-\x7E]{1,128}$/', $idempotencyKey)) {
            return $this->error($request, 'invalid_idempotency_key', 'Header Idempotency-Key wajib berupa maksimal 128 karakter yang dapat dicetak.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existingRun = KompenResponHubApiSyncRun::query()
            ->where('api_client_id', $client->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingRun instanceof KompenResponHubApiSyncRun) {
            if (! $this->matchesSelector($existingRun, $selector)) {
                return $this->error($request, 'idempotency_conflict', 'Idempotency-Key sudah dipakai untuk selector lain.', Response::HTTP_CONFLICT);
            }

            return $this->syncRunResponse($request, $existingRun, Response::HTTP_OK);
        }

        $query = KompenResponHubStudent::query()
            ->where('periode_semester', $selector['periode']);

        if ($selector['type'] === 'kelas') {
            $query->where('kelas', $selector['kelas']);
        }

        if (! $query->exists()) {
            return $this->error($request, 'not_found', 'Tidak ada data Kompen dan Respon untuk selector yang diminta.', Response::HTTP_NOT_FOUND);
        }

        try {
            $run = KompenResponHubApiSyncRun::query()->create([
                'api_client_id' => $client->id,
                'run_id' => (string) Str::ulid(),
                'idempotency_key' => $idempotencyKey,
                'selector_type' => $selector['type'],
                'periode_semester' => $selector['periode'],
                'kelas' => $selector['type'] === 'kelas' ? $selector['kelas'] : null,
                'status' => 'queued',
            ]);
        } catch (UniqueConstraintViolationException) {
            $run = KompenResponHubApiSyncRun::query()
                ->where('api_client_id', $client->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (! $run instanceof KompenResponHubApiSyncRun || ! $this->matchesSelector($run, $selector)) {
                return $this->error($request, 'idempotency_conflict', 'Idempotency-Key sudah dipakai untuk selector lain.', Response::HTTP_CONFLICT);
            }

            return $this->syncRunResponse($request, $run, Response::HTTP_OK);
        }

        GenerateKompenResponHubApiSyncArtifact::dispatch($run->id)
            ->onQueue(config('kompen-respon-hub.api_sync.queue'));

        return $this->syncRunResponse($request, $run, Response::HTTP_ACCEPTED);
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

    /** @param array{type: string, periode: string, kelas?: string|null} $selector */
    private function matchesSelector(KompenResponHubApiSyncRun $run, array $selector): bool
    {
        return $run->selector_type === $selector['type']
            && $run->periode_semester === $selector['periode']
            && $run->kelas === ($selector['type'] === 'kelas' ? $selector['kelas'] ?? null : null);
    }

    private function client(Request $request): KompenResponHubApiClient
    {
        $client = $request->attributes->get('sikompen_api_client');

        abort_unless($client instanceof KompenResponHubApiClient, Response::HTTP_UNAUTHORIZED);

        return $client;
    }

    private function findOwnedRun(Request $request, string $runId): ?KompenResponHubApiSyncRun
    {
        return KompenResponHubApiSyncRun::query()
            ->where('api_client_id', $this->client($request)->id)
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
            'format' => 'ndjson',
            'compression' => 'gzip',
            'record_count' => $run->record_count,
            'artifact_sha256' => $run->artifact_checksum,
            'artifact_size_bytes' => $run->artifact_size_bytes,
            'download_url' => $run->status === 'ready' ? route('api.v1.kompen-respon.sync-runs.download', $run->run_id) : null,
            'expires_at' => $run->expires_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
            'failure_code' => $run->failure_code,
        ];
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

    private function error(Request $request, string $code, string $message, int $status): Response
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
        ]);
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->attributes->get('sikompen_api_request_id');

        return is_string($requestId) ? $requestId : (string) Str::ulid();
    }
}
