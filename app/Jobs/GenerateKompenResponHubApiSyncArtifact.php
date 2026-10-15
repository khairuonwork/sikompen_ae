<?php

namespace App\Jobs;

use App\Http\Resources\KompenResponHubOutboundStudentResource;
use App\Models\KompenResponHubApiSyncRun;
use App\Models\KompenResponHubStudent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateKompenResponHubApiSyncArtifact implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

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
            'failure_code' => null,
        ])->save();

        $artifactPath = sprintf('private/kompen-respon-hub/api-sync/%s.ndjson.gz', $run->run_id);

        try {
            Storage::disk('local')->makeDirectory('private/kompen-respon-hub/api-sync');
            $absolutePath = Storage::disk('local')->path($artifactPath);
            $handle = gzopen($absolutePath, 'wb9');

            if ($handle === false) {
                throw new RuntimeException('Artifact tidak dapat dibuat.');
            }

            $count = 0;
            $request = Request::create('/api/v1/internal-artifact');
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
            }

            gzclose($handle);
            $checksum = hash_file('sha256', $absolutePath);
            $size = filesize($absolutePath);

            if (! is_string($checksum) || ! is_int($size)) {
                throw new RuntimeException('Metadata artifact tidak dapat dibaca.');
            }

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
            if (isset($handle) && is_resource($handle)) {
                gzclose($handle);
            }

            Storage::disk('local')->delete($artifactPath);

            $run->forceFill([
                'status' => 'failed',
                'failure_code' => 'artifact_generation_failed',
                'completed_at' => now(),
            ])->save();

            report($exception);
            Log::warning('Gagal membuat artifact API Kompen Respon.', [
                'sync_run_id' => $run->run_id,
                'exception' => $exception::class,
            ]);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->syncRunId;
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
