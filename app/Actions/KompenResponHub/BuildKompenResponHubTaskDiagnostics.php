<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActiveStudentImportTask;
use App\Models\KompenResponHubExportTask;
use App\Models\KompenResponHubImportTask;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BuildKompenResponHubTaskDiagnostics
{
    /**
     * @return list<array{id: int, task_type: 'kompen_respon'|'active_students'|'export', label: string, status: 'failed'|'stalled', progress_message: string, error_message: string|null, queued_at: string|null, started_at: string|null, failed_at: string|null, can_retry: bool}>
     */
    public function execute(): array
    {
        $threshold = now()->subMinutes(max(15, (int) config('kompen-respon-hub.queue.stalled_task_minutes', 15)));

        return collect()
            ->concat($this->importTasks($threshold))
            ->concat($this->activeStudentImportTasks($threshold))
            ->concat($this->exportTasks($threshold))
            ->sortByDesc('failed_at')
            ->sortByDesc('started_at')
            ->values()
            ->take(100)
            ->all();
    }

    /**
     * @return Collection<int, array{id: int, task_type: 'kompen_respon', label: string, status: 'failed'|'stalled', progress_message: string, error_message: string|null, queued_at: string|null, started_at: string|null, failed_at: string|null, can_retry: bool}>
     */
    private function importTasks(CarbonInterface $threshold): Collection
    {
        return KompenResponHubImportTask::query()
            ->where(fn ($query) => $query->where('status', KompenResponHubImportTask::STATUS_FAILED)->orWhere($this->stalledTaskCondition($threshold)))
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (KompenResponHubImportTask $task): array => $this->taskPayload(
                $task->id,
                'kompen_respon',
                $task->original_filename,
                $task->status,
                $task->progress_message,
                $task->error_message,
                $task->queued_at,
                $task->started_at,
                $task->failed_at,
            ));
    }

    /**
     * @return Collection<int, array{id: int, task_type: 'active_students', label: string, status: 'failed'|'stalled', progress_message: string, error_message: string|null, queued_at: string|null, started_at: string|null, failed_at: string|null, can_retry: bool}>
     */
    private function activeStudentImportTasks(CarbonInterface $threshold): Collection
    {
        return KompenResponHubActiveStudentImportTask::query()
            ->where(fn ($query) => $query->where('status', KompenResponHubActiveStudentImportTask::StatusFailed)->orWhere($this->stalledTaskCondition($threshold)))
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (KompenResponHubActiveStudentImportTask $task): array => $this->taskPayload(
                $task->id,
                'active_students',
                $task->original_filename,
                $task->status,
                $task->progress_message,
                $task->error_message,
                $task->queued_at,
                $task->started_at,
                $task->failed_at,
            ));
    }

    /**
     * @return Collection<int, array{id: int, task_type: 'export', label: string, status: 'failed'|'stalled', progress_message: string, error_message: string|null, queued_at: string|null, started_at: string|null, failed_at: string|null, can_retry: bool}>
     */
    private function exportTasks(CarbonInterface $threshold): Collection
    {
        return KompenResponHubExportTask::query()
            ->where(fn ($query) => $query->where('status', KompenResponHubExportTask::StatusFailed)->orWhere($this->stalledTaskCondition($threshold)))
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (KompenResponHubExportTask $task): array => $this->taskPayload(
                $task->id,
                'export',
                "Ekspor {$task->resource} {$task->format}",
                $task->status,
                $task->progress_message,
                $task->error_message,
                $task->queued_at,
                $task->started_at,
                $task->failed_at,
            ));
    }

    private function stalledTaskCondition(CarbonInterface $threshold): \Closure
    {
        return function ($query) use ($threshold): void {
            $query
                ->whereIn('status', [
                    KompenResponHubImportTask::STATUS_QUEUED,
                    KompenResponHubImportTask::STATUS_PROCESSING,
                ])
                ->where(function ($taskQuery) use ($threshold): void {
                    $taskQuery
                        ->where('started_at', '<=', $threshold)
                        ->orWhere(function ($queuedQuery) use ($threshold): void {
                            $queuedQuery->whereNull('started_at')->where('queued_at', '<=', $threshold);
                        });
                });
        };
    }

    /**
     * @param  'kompen_respon'|'active_students'|'export'  $taskType
     * @param  'queued'|'processing'|'failed'  $status
     * @return array{id: int, task_type: 'kompen_respon'|'active_students'|'export', label: string, status: 'failed'|'stalled', progress_message: string, error_message: string|null, queued_at: string|null, started_at: string|null, failed_at: string|null, can_retry: bool}
     */
    private function taskPayload(
        int $id,
        string $taskType,
        string $label,
        string $status,
        string $progressMessage,
        ?string $errorMessage,
        ?CarbonInterface $queuedAt,
        ?CarbonInterface $startedAt,
        ?CarbonInterface $failedAt,
    ): array {
        return [
            'id' => $id,
            'task_type' => $taskType,
            'label' => $label,
            'status' => $status === KompenResponHubImportTask::STATUS_FAILED ? 'failed' : 'stalled',
            'progress_message' => $progressMessage,
            'error_message' => $errorMessage,
            'queued_at' => $queuedAt?->toIso8601String(),
            'started_at' => $startedAt?->toIso8601String(),
            'failed_at' => $failedAt?->toIso8601String(),
            'can_retry' => $status === KompenResponHubImportTask::STATUS_FAILED,
        ];
    }
}
