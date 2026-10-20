<?php

namespace App\Console\Commands;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Models\KompenResponHubActiveStudentImportTask;
use App\Models\KompenResponHubApiSyncRun;
use App\Models\KompenResponHubExportTask;
use App\Models\KompenResponHubImportTask;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

#[Signature('sikompen:reconcile-tasks')]
#[Description('Mark stalled Sikompen import, export, and API sync tasks as failed.')]
class ReconcileKompenResponHubTasks extends Command
{
    public function handle(RecordKompenResponHubActivity $activity): int
    {
        $processingAfterMinutes = max(15, (int) config('kompen-respon-hub.queue.stalled_task_minutes', 15));
        $queuedAfterMinutes = max($processingAfterMinutes, (int) config('kompen-respon-hub.queue.queued_task_minutes', 60));
        $processingThreshold = now()->subMinutes($processingAfterMinutes);
        $queuedThreshold = now()->subMinutes($queuedAfterMinutes);
        $importCount = $this->reconcileImports($processingThreshold, $queuedThreshold, $processingAfterMinutes, $queuedAfterMinutes, $activity);
        $activeStudentImportCount = $this->reconcileActiveStudentImports($processingThreshold, $queuedThreshold, $processingAfterMinutes, $queuedAfterMinutes, $activity);
        $exportCount = $this->reconcileExports($processingThreshold, $queuedThreshold, $processingAfterMinutes, $queuedAfterMinutes, $activity);
        $apiSyncCount = $this->reconcileApiSyncRuns($processingThreshold, $queuedThreshold);

        $this->components->info("{$importCount} task workbook, {$activeStudentImportCount} task roster, {$exportCount} task ekspor, dan {$apiSyncCount} run API macet ditandai gagal.");

        return self::SUCCESS;
    }

    private function reconcileImports(
        \DateTimeInterface $processingThreshold,
        \DateTimeInterface $queuedThreshold,
        int $processingAfterMinutes,
        int $queuedAfterMinutes,
        RecordKompenResponHubActivity $activity,
    ): int {
        $count = 0;

        $this->stalledImportTasks($processingThreshold, $queuedThreshold)
            ->chunkById(200, function ($tasks) use (&$count, $activity, $processingAfterMinutes, $queuedAfterMinutes): void {
                foreach ($tasks as $task) {
                    $sourceWorkbookRemoved = $this->deleteSourceWorkbook($task->stored_path, 'kompen-respon-hub/imports/');
                    $message = 'Proses impor melewati batas waktu pemrosesan dan ditandai gagal. File unggahan dibersihkan demi konsistensi data. Unggah ulang workbook bila masih diperlukan.';
                    $task->update([
                        'status' => KompenResponHubImportTask::STATUS_FAILED,
                        'progress' => 100,
                        'progress_message' => 'Impor berhenti karena waktu pemrosesan terlewati.',
                        'error_message' => $message,
                        'failed_at' => now(),
                    ]);

                    $activity->execute(
                        'import.timed_out',
                        'import_task',
                        (string) $task->id,
                        null,
                        reason: $message,
                        metadata: [
                            'original_filename' => $task->original_filename,
                            'processing_after_minutes' => $processingAfterMinutes,
                            'queued_after_minutes' => $queuedAfterMinutes,
                            'source_workbook_removed' => $sourceWorkbookRemoved,
                        ],
                        subjectName: $task->original_filename,
                    );
                    $count++;
                }
            });

        return $count;
    }

    private function reconcileActiveStudentImports(
        \DateTimeInterface $processingThreshold,
        \DateTimeInterface $queuedThreshold,
        int $processingAfterMinutes,
        int $queuedAfterMinutes,
        RecordKompenResponHubActivity $activity,
    ): int {
        $count = 0;

        $this->stalledActiveStudentImportTasks($processingThreshold, $queuedThreshold)
            ->chunkById(200, function ($tasks) use (&$count, $activity, $processingAfterMinutes, $queuedAfterMinutes): void {
                foreach ($tasks as $task) {
                    $sourceWorkbookRemoved = $this->deleteSourceWorkbook($task->stored_path, 'kompen-respon-hub/imports/active-students/');
                    $message = 'Proses impor daftar mahasiswa aktif melewati batas waktu pemrosesan dan ditandai gagal. File unggahan dibersihkan demi konsistensi data. Unggah ulang file bila masih diperlukan.';
                    $task->update([
                        'status' => KompenResponHubActiveStudentImportTask::StatusFailed,
                        'progress' => 100,
                        'progress_message' => 'Impor roster berhenti karena waktu pemrosesan terlewati.',
                        'error_message' => $message,
                        'failed_at' => now(),
                    ]);

                    $activity->execute(
                        'active_students.timed_out',
                        'active_student_import_task',
                        (string) $task->id,
                        null,
                        reason: $message,
                        metadata: [
                            'original_filename' => $task->original_filename,
                            'processing_after_minutes' => $processingAfterMinutes,
                            'queued_after_minutes' => $queuedAfterMinutes,
                            'source_workbook_removed' => $sourceWorkbookRemoved,
                        ],
                        subjectName: $task->original_filename,
                    );
                    $count++;
                }
            });

        return $count;
    }

    private function reconcileExports(
        \DateTimeInterface $processingThreshold,
        \DateTimeInterface $queuedThreshold,
        int $processingAfterMinutes,
        int $queuedAfterMinutes,
        RecordKompenResponHubActivity $activity,
    ): int {
        $count = 0;

        $this->stalledExportTasks($processingThreshold, $queuedThreshold)
            ->chunkById(200, function ($tasks) use (&$count, $activity, $processingAfterMinutes, $queuedAfterMinutes): void {
                foreach ($tasks as $task) {
                    $message = 'Proses ekspor melewati batas waktu pemrosesan dan ditandai gagal. Buat permintaan ekspor baru bila masih diperlukan.';
                    $task->update([
                        'status' => KompenResponHubExportTask::StatusFailed,
                        'progress' => 100,
                        'progress_message' => 'Ekspor berhenti karena waktu pemrosesan terlewati.',
                        'error_message' => $message,
                        'failed_at' => now(),
                    ]);

                    $activity->execute(
                        'export.timed_out',
                        'export',
                        (string) $task->id,
                        null,
                        reason: $message,
                        metadata: [
                            ...$task->activityMetadata(),
                            'processing_after_minutes' => $processingAfterMinutes,
                            'queued_after_minutes' => $queuedAfterMinutes,
                        ],
                    );
                    $count++;
                }
            });

        return $count;
    }

    private function reconcileApiSyncRuns(
        \DateTimeInterface $processingThreshold,
        \DateTimeInterface $queuedThreshold,
    ): int {
        $count = 0;

        $this->stalledApiSyncRuns($processingThreshold, $queuedThreshold)
            ->chunkById(200, function ($runs) use (&$count): void {
                foreach ($runs as $run) {
                    $updated = KompenResponHubApiSyncRun::query()
                        ->whereKey($run->id)
                        ->where('status', $run->status)
                        ->update([
                            'status' => 'failed',
                            'failure_code' => 'processing_timeout',
                            'completed_at' => now(),
                            'updated_at' => now(),
                        ]);

                    if ($updated === 1) {
                        Storage::disk('local')->delete(sprintf('kompen-respon-hub/api-sync/%s.ndjson.gz', $run->run_id));
                        $count++;
                    }
                }
            });

        return $count;
    }

    /** @return Builder<KompenResponHubImportTask> */
    private function stalledImportTasks(\DateTimeInterface $processingThreshold, \DateTimeInterface $queuedThreshold): Builder
    {
        return KompenResponHubImportTask::query()
            ->whereIn('status', [
                KompenResponHubImportTask::STATUS_QUEUED,
                KompenResponHubImportTask::STATUS_PROCESSING,
            ])
            ->where(function (Builder $query) use ($processingThreshold, $queuedThreshold): void {
                $query
                    ->where(function (Builder $processingQuery) use ($processingThreshold): void {
                        $processingQuery
                            ->where('status', KompenResponHubImportTask::STATUS_PROCESSING)
                            ->where('started_at', '<=', $processingThreshold);
                    })
                    ->orWhere(function (Builder $queuedQuery) use ($queuedThreshold): void {
                        $queuedQuery
                            ->where('status', KompenResponHubImportTask::STATUS_QUEUED)
                            ->where('queued_at', '<=', $queuedThreshold);
                    });
            });
    }

    /** @return Builder<KompenResponHubExportTask> */
    private function stalledExportTasks(\DateTimeInterface $processingThreshold, \DateTimeInterface $queuedThreshold): Builder
    {
        return KompenResponHubExportTask::query()
            ->whereIn('status', [
                KompenResponHubExportTask::StatusQueued,
                KompenResponHubExportTask::StatusProcessing,
            ])
            ->where(function (Builder $query) use ($processingThreshold, $queuedThreshold): void {
                $query
                    ->where(function (Builder $processingQuery) use ($processingThreshold): void {
                        $processingQuery
                            ->where('status', KompenResponHubExportTask::StatusProcessing)
                            ->where('started_at', '<=', $processingThreshold);
                    })
                    ->orWhere(function (Builder $queuedQuery) use ($queuedThreshold): void {
                        $queuedQuery
                            ->where('status', KompenResponHubExportTask::StatusQueued)
                            ->where('queued_at', '<=', $queuedThreshold);
                    });
            });
    }

    /** @return Builder<KompenResponHubActiveStudentImportTask> */
    private function stalledActiveStudentImportTasks(\DateTimeInterface $processingThreshold, \DateTimeInterface $queuedThreshold): Builder
    {
        return KompenResponHubActiveStudentImportTask::query()
            ->whereIn('status', [
                KompenResponHubActiveStudentImportTask::StatusQueued,
                KompenResponHubActiveStudentImportTask::StatusProcessing,
            ])
            ->where(function (Builder $query) use ($processingThreshold, $queuedThreshold): void {
                $query
                    ->where(function (Builder $processingQuery) use ($processingThreshold): void {
                        $processingQuery
                            ->where('status', KompenResponHubActiveStudentImportTask::StatusProcessing)
                            ->where('started_at', '<=', $processingThreshold);
                    })
                    ->orWhere(function (Builder $queuedQuery) use ($queuedThreshold): void {
                        $queuedQuery
                            ->where('status', KompenResponHubActiveStudentImportTask::StatusQueued)
                            ->where('queued_at', '<=', $queuedThreshold);
                    });
            });
    }

    /** @return Builder<KompenResponHubApiSyncRun> */
    private function stalledApiSyncRuns(\DateTimeInterface $processingThreshold, \DateTimeInterface $queuedThreshold): Builder
    {
        return KompenResponHubApiSyncRun::query()
            ->whereIn('status', ['queued', 'processing'])
            ->where(function (Builder $query) use ($processingThreshold, $queuedThreshold): void {
                $query
                    ->where(function (Builder $processingQuery) use ($processingThreshold): void {
                        $processingQuery
                            ->where('status', 'processing')
                            ->where('started_at', '<=', $processingThreshold);
                    })
                    ->orWhere(function (Builder $queuedQuery) use ($queuedThreshold): void {
                        $queuedQuery
                            ->where('status', 'queued')
                            ->where('created_at', '<=', $queuedThreshold);
                    });
            });
    }

    private function deleteSourceWorkbook(string $storedPath, string $trustedDirectory): bool
    {
        if (! str_starts_with($storedPath, $trustedDirectory)) {
            return false;
        }

        try {
            return Storage::disk('local')->delete($storedPath);
        } catch (\Throwable) {
            return false;
        }
    }
}
