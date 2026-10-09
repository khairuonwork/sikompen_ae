<?php

namespace App\Jobs;

use App\Actions\KompenResponHub\BuildKompenResponHubExport;
use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubExportTask;
use DomainException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateKompenResponHubExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $exportTaskId) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('sikompen:exports'))->releaseAfter(15)->expireAfter(660)];
    }

    public function handle(BuildKompenResponHubExport $builder, RecordKompenResponHubActivity $activity): void
    {
        $task = KompenResponHubExportTask::query()->find($this->exportTaskId);

        if ($task === null || ! $task->isActive()) {
            return;
        }

        $task->update([
            'status' => KompenResponHubExportTask::StatusProcessing,
            'progress' => 20,
            'progress_message' => 'Menyiapkan data untuk diekspor.',
            'started_at' => now(),
        ]);

        try {
            $result = $builder->execute($task);
        } catch (DomainException $exception) {
            $this->markAsFailed($task, $exception->getMessage(), $activity);

            return;
        } catch (Throwable $exception) {
            $this->reportFailure($task, $exception);

            $this->markAsFailed(
                $task,
                'File tidak dapat dibuat. Coba ulangi dengan filter yang lebih spesifik atau hubungi administrator.',
                $activity,
            );

            return;
        }

        $task->refresh();
        if (! $task->isActive()) {
            Storage::disk('local')->delete($result['output_path']);

            return;
        }

        $wasCompleted = KompenResponHubExportTask::query()
            ->whereKey($task->id)
            ->whereIn('status', [
                KompenResponHubExportTask::StatusQueued,
                KompenResponHubExportTask::StatusProcessing,
            ])
            ->update([
                'status' => KompenResponHubExportTask::StatusCompleted,
                'progress' => 100,
                'progress_message' => 'File siap diunduh.',
                'output_path' => $result['output_path'],
                'download_filename' => $result['download_filename'],
                'completed_at' => now(),
                'expires_at' => now()->addHours(max(1, (int) config('kompen-respon-hub.retention.export_hours', 24))),
            ]);

        if ($wasCompleted !== 1) {
            Storage::disk('local')->delete($result['output_path']);

            return;
        }

        $task->refresh();

        $admin = $task->requested_by_admin_id === null
            ? null
            : KompenResponHubAdmin::query()->find($task->requested_by_admin_id);

        $activity->execute(
            'export.completed',
            'export',
            (string) $task->id,
            $admin,
            null,
            period: $task->filters['periode_semester'] ?? null,
            metadata: $task->activityMetadata(),
        );
    }

    public function failed(?Throwable $exception): void
    {
        $task = KompenResponHubExportTask::query()->find($this->exportTaskId);

        if ($task === null || $task->status === KompenResponHubExportTask::StatusCompleted) {
            return;
        }

        if ($exception !== null) {
            $this->reportFailure($task, $exception);
        }

        $this->markAsFailed(
            $task,
            'File tidak dapat dibuat. Coba ulangi dengan filter yang lebih spesifik atau hubungi administrator.',
            app(RecordKompenResponHubActivity::class),
        );
    }

    private function markAsFailed(
        KompenResponHubExportTask $task,
        string $message,
        RecordKompenResponHubActivity $activity,
    ): void {
        $wasFailed = KompenResponHubExportTask::query()
            ->whereKey($task->id)
            ->whereIn('status', [
                KompenResponHubExportTask::StatusQueued,
                KompenResponHubExportTask::StatusProcessing,
            ])
            ->update([
                'status' => KompenResponHubExportTask::StatusFailed,
                'progress' => 100,
                'progress_message' => 'Ekspor tidak dapat dibuat.',
                'error_message' => $message,
                'failed_at' => now(),
            ]);

        if ($wasFailed !== 1) {
            return;
        }

        $task->refresh();

        $admin = $task->requested_by_admin_id === null
            ? null
            : KompenResponHubAdmin::query()->find($task->requested_by_admin_id);
        $activity->execute(
            'export.failed',
            'export',
            (string) $task->id,
            $admin,
            period: is_string($task->filters['periode_semester'] ?? null) ? $task->filters['periode_semester'] : null,
            reason: $message,
            metadata: $task->activityMetadata(),
        );
    }

    private function reportFailure(KompenResponHubExportTask $task, Throwable $exception): void
    {
        Log::error('Sikompen export generation failed.', [
            'export_task_id' => $task->id,
            'resource' => $task->resource,
            'format' => $task->format,
            'filters' => $task->filters,
            'exception' => $exception,
        ]);
    }
}
