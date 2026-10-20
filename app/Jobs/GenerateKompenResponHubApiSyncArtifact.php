<?php

namespace App\Jobs;

use App\Http\Resources\KompenResponHubOutboundStudentResource;
use App\Models\KompenResponHubApiSyncRun;
use App\Models\KompenResponHubStudent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateKompenResponHubApiSyncArtifact implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $syncRunId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $run = KompenResponHubApiSyncRun::query()->find($this->syncRunId);

        if (! $run instanceof KompenResponHubApiSyncRun || $run->status !== 'queued') {
            return;
        }

        $run->forceFill([
            'status' => 'processing',
            'started_at' => now(),
            'snapshot_at' => now(),
            'failure_code' => null,
            'completed_at' => null,
        ])->save();

        $artifactPath = sprintf('kompen-respon-hub/api-sync/%s.ndjson.gz', $run->run_id);
        $handle = null;

        try {
            Storage::disk('local')->makeDirectory('kompen-respon-hub/api-sync');
            $absolutePath = Storage::disk('local')->path($artifactPath);
            $handle = gzopen($absolutePath, 'wb9');

            if ($handle === false) {
                throw new RuntimeException('Artifact tidak dapat dibuat.');
            }

            $count = $this->writeSnapshot($run, $handle);
            gzclose($handle);
            $handle = null;

            $checksum = hash_file('sha256', $absolutePath);
            $size = filesize($absolutePath);

            if (! is_string($checksum) || ! is_int($size)) {
                throw new RuntimeException('Metadata artifact tidak dapat dibaca.');
            }

            $this->assertFinalArtifactSize($size);

            $run->forceFill([
                'status' => 'ready',
                'artifact_path' => $artifactPath,
                'artifact_checksum' => $checksum,
                'record_count' => $count,
                'artifact_size_bytes' => $size,
                'completed_at' => now(),
                'expires_at' => now()->addHours((int) config('kompen-respon-hub.api_sync.artifact_retention_hours')),
            ])->save();
        } catch (Throwable $exception) {
            if (is_resource($handle)) {
                gzclose($handle);
            }

            Storage::disk('local')->delete($artifactPath);

            $run->forceFill([
                'status' => 'queued',
                'started_at' => null,
                'snapshot_at' => null,
                'failure_code' => null,
                'completed_at' => null,
            ])->save();

            Log::warning('Pembuatan artifact API Kompen Respon gagal dan akan dicoba ulang.', [
                'sync_run_id' => $run->run_id,
                'attempt' => $this->attempts(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $run = KompenResponHubApiSyncRun::query()->find($this->syncRunId);

        if (! $run instanceof KompenResponHubApiSyncRun || $run->status === 'ready') {
            return;
        }

        Storage::disk('local')->delete(sprintf('kompen-respon-hub/api-sync/%s.ndjson.gz', $run->run_id));

        $run->forceFill([
            'status' => 'failed',
            'failure_code' => str_starts_with($exception->getMessage(), 'artifact_size_limit')
                ? 'artifact_too_large'
                : 'artifact_generation_failed',
            'completed_at' => now(),
        ])->save();

        Log::error('Pembuatan artifact API Kompen Respon gagal permanen.', [
            'sync_run_id' => $run->run_id,
            'exception' => $exception::class,
        ]);
    }

    public function uniqueId(): string
    {
        return (string) $this->syncRunId;
    }

    /** @param resource $handle */
    private function writeSnapshot(KompenResponHubApiSyncRun $run, $handle): int
    {
        $count = 0;
        $request = Request::create('/api/sikompen/v1/internal-artifact');

        DB::connection($run->getConnectionName())->transaction(function () use ($run, $handle, $request, &$count): void {
            $query = KompenResponHubStudent::query()
                ->with(['progress', 'summaryOverride'])
                ->where('periode_semester', $run->periode_semester)
                ->orderBy('id');

            if ($run->selector_type === 'kelas' && $run->kelas !== null) {
                $query->where('kelas', $run->kelas);
            }

            foreach ($query->lazyById(200) as $student) {
                $line = json_encode(
                    (new KompenResponHubOutboundStudentResource($student))->toArray($request),
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                )."\n";
                $this->writeLine($handle, $line);
                $count++;

                if ($count % 200 === 0) {
                    $this->assertUncompressedSize($handle);
                }
            }

            $this->assertUncompressedSize($handle);
        }, 3);

        return $count;
    }

    /** @param resource $handle */
    private function assertUncompressedSize($handle): void
    {
        $maximumBytes = (int) config('kompen-respon-hub.api_sync.max_uncompressed_bytes');

        if ($maximumBytes < 1) {
            return;
        }

        if (gztell($handle) > $maximumBytes) {
            throw new RuntimeException('artifact_size_limit: Artifact melebihi batas ukuran yang diizinkan.');
        }
    }

    private function assertFinalArtifactSize(int $size): void
    {
        $maximumBytes = (int) config('kompen-respon-hub.api_sync.max_artifact_bytes');

        if ($maximumBytes > 0 && $size > $maximumBytes) {
            throw new RuntimeException('artifact_size_limit: Artifact melebihi batas ukuran yang diizinkan.');
        }
    }

    /** @param resource $handle */
    private function writeLine($handle, string $line): void
    {
        $written = gzwrite($handle, $line);

        if ($written !== strlen($line)) {
            throw new RuntimeException('Artifact tidak dapat ditulis secara lengkap.');
        }
    }
}
