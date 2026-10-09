<?php

namespace App\Console\Commands;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Models\KompenResponHubExportTask;
use App\Models\KompenResponHubImportTask;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

#[Signature('sikompen:reconcile-tasks')]
#[Description('Mark stalled Sikompen import and export tasks as failed.')]
class ReconcileKompenResponHubTasks extends Command
{
    public function handle(RecordKompenResponHubActivity $activity): int
    {
        $stalledAfterMinutes = max(15, (int) config('kompen-respon-hub.queue.stalled_task_minutes', 15));
        $threshold = now()->subMinutes($stalledAfterMinutes);
        $importCount = $this->reconcileImports($threshold, $stalledAfterMinutes, $activity);
        $exportCount = $this->reconcileExports($threshold, $stalledAfterMinutes, $activity);

        $this->components->info("{$importCount} task impor dan {$exportCount} task ekspor macet ditandai gagal.");

        return self::SUCCESS;
    }

    private function reconcileImports(
        \DateTimeInterface $threshold,
        int $stalledAfterMinutes,
        RecordKompenResponHubActivity $activity,
    ): int {
        $count = 0;

        $this->stalledImportTasks($threshold)
            ->chunkById(200, function ($tasks) use (&$count, $activity, $stalledAfterMinutes): void {
                foreach ($tasks as $task) {
                    $message = 'Proses impor melewati batas waktu pemrosesan dan ditandai gagal. Unggah ulang workbook bila masih diperlukan.';
                    $task->update([
                        'status' => KompenResponHubImportTask::STATUS_FAILED,
                        'progress' => 100,
                        'progress_message' => 'Impor berhenti karena waktu pemrosesan terlewati.',
                        'error_message' => $message,
                        'failed_at' => now(),
                    ]);

                    if (str_starts_with($task->stored_path, 'kompen-respon-hub/imports/')) {
                        Storage::disk('local')->delete($task->stored_path);
                    }

                    $activity->execute(
                        'import.timed_out',
                        'import_task',
                        (string) $task->id,
                        null,
                        reason: $message,
                        metadata: [
                            'original_filename' => $task->original_filename,
                            'stalled_after_minutes' => $stalledAfterMinutes,
                        ],
                        subjectName: $task->original_filename,
                    );
                    $count++;
                }
            });

        return $count;
    }

    private function reconcileExports(
        \DateTimeInterface $threshold,
        int $stalledAfterMinutes,
        RecordKompenResponHubActivity $activity,
    ): int {
        $count = 0;

        $this->stalledExportTasks($threshold)
            ->chunkById(200, function ($tasks) use (&$count, $activity, $stalledAfterMinutes): void {
                foreach ($tasks as $task) {
                    $message = 'Proses ekspor melewati batas waktu pemrosesan dan ditandai gagal. Buat permintaan ekspor baru bila masih diperlukan.';
                    $task->update([
                        'status' => KompenResponHubExportTask::StatusFailed,
                        'progress' => 100,
                        'progress_message' => 'Ekspor berhenti karena waktu pemrosesan terlewati.',
                        'error_message' => $message,
                        'failed_at' => now(),
                    ]);

                    if ($task->output_path !== null && str_starts_with($task->output_path, 'kompen-respon-hub/exports/')) {
                        Storage::disk('local')->delete($task->output_path);
                    }

                    $activity->execute(
                        'export.timed_out',
                        'export',
                        (string) $task->id,
                        null,
                        reason: $message,
                        metadata: [
                            ...$task->activityMetadata(),
                            'stalled_after_minutes' => $stalledAfterMinutes,
                        ],
                    );
                    $count++;
                }
            });

        return $count;
    }

    /** @return Builder<KompenResponHubImportTask> */
    private function stalledImportTasks(\DateTimeInterface $threshold): Builder
    {
        return KompenResponHubImportTask::query()
            ->whereIn('status', [
                KompenResponHubImportTask::STATUS_QUEUED,
                KompenResponHubImportTask::STATUS_PROCESSING,
            ])
            ->where(function (Builder $query) use ($threshold): void {
                $query
                    ->where('started_at', '<=', $threshold)
                    ->orWhere(function (Builder $queuedQuery) use ($threshold): void {
                        $queuedQuery
                            ->whereNull('started_at')
                            ->where('queued_at', '<=', $threshold);
                    });
            });
    }

    /** @return Builder<KompenResponHubExportTask> */
    private function stalledExportTasks(\DateTimeInterface $threshold): Builder
    {
        return KompenResponHubExportTask::query()
            ->whereIn('status', [
                KompenResponHubExportTask::StatusQueued,
                KompenResponHubExportTask::StatusProcessing,
            ])
            ->where(function (Builder $query) use ($threshold): void {
                $query
                    ->where('started_at', '<=', $threshold)
                    ->orWhere(function (Builder $queuedQuery) use ($threshold): void {
                        $queuedQuery
                            ->whereNull('started_at')
                            ->where('queued_at', '<=', $threshold);
                    });
            });
    }
}
