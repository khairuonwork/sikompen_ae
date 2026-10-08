<?php

namespace App\Console\Commands;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Models\KompenResponHubImportTask;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sikompen:purge-import-tasks')]
#[Description('Remove completed and failed Sikompen import task records after their retention period.')]
class PurgeKompenResponHubImportTasks extends Command
{
    public function handle(RecordKompenResponHubActivity $activity): int
    {
        $retentionDays = max(1, (int) config('kompen-respon-hub.retention.import_task_days', 30));
        $threshold = now()->subDays($retentionDays);
        $deletedCount = 0;

        KompenResponHubImportTask::query()
            ->where(function ($query) use ($threshold): void {
                $query
                    ->where(function ($completedQuery) use ($threshold): void {
                        $completedQuery
                            ->where('status', KompenResponHubImportTask::STATUS_COMPLETED)
                            ->where('completed_at', '<=', $threshold);
                    })
                    ->orWhere(function ($failedQuery) use ($threshold): void {
                        $failedQuery
                            ->where('status', KompenResponHubImportTask::STATUS_FAILED)
                            ->where('failed_at', '<=', $threshold);
                    });
            })
            ->chunkById(200, function ($tasks) use (&$deletedCount): void {
                foreach ($tasks as $task) {
                    $task->delete();
                    $deletedCount++;
                }
            });

        if ($deletedCount > 0) {
            $activity->execute(
                'maintenance.import_tasks_purged',
                'import_task',
                null,
                null,
                reason: "Retensi {$retentionDays} hari diterapkan pada task impor terminal.",
                metadata: ['deleted_count' => $deletedCount, 'retention_days' => $retentionDays],
            );
        }

        $this->components->info("{$deletedCount} task impor terminal dihapus.");

        return self::SUCCESS;
    }
}
