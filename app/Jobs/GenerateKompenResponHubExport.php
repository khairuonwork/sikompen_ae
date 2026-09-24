<?php

namespace App\Jobs;

use App\Actions\KompenResponHub\BuildKompenResponHubExport;
use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubExportTask;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Middleware\WithoutOverlapping;
use Illuminate\Foundation\Queue\Queueable;
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
        } catch (Throwable) {
            $task->update([
                'status' => KompenResponHubExportTask::StatusFailed,
                'progress' => 100,
                'progress_message' => 'Ekspor gagal diproses.',
                'error_message' => 'File tidak dapat dibuat. Coba ulangi dengan filter yang lebih spesifik atau hubungi administrator.',
                'failed_at' => now(),
            ]);

            return;
        }

        $task->update([
            'status' => KompenResponHubExportTask::StatusCompleted,
            'progress' => 100,
            'progress_message' => 'File siap diunduh.',
            'output_path' => $result['output_path'],
            'download_filename' => $result['download_filename'],
            'completed_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

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
            metadata: ['resource' => $task->resource, 'format' => $task->format],
            subjectName: $task->download_filename,
        );
    }
}
