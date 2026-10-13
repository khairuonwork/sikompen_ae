<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActiveStudentImportTask;
use App\Models\KompenResponHubExportTask;
use App\Models\KompenResponHubImportTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RetryKompenResponHubTask
{
    /**
     * @param  'kompen_respon'|'active_students'|'export'  $taskType
     * @return array{task_type: 'kompen_respon'|'active_students'|'export', task_id: int, subject_name: string, periode_semester: string|null}
     */
    public function execute(string $taskType, int $taskId, string $requestSessionId): array
    {
        return DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($requestSessionId, $taskId, $taskType): array {
                return match ($taskType) {
                    'kompen_respon' => $this->retryImport($taskId),
                    'active_students' => $this->retryActiveStudentImport($taskId),
                    'export' => $this->retryExport($taskId, $requestSessionId),
                };
            });
    }

    /** @return array{task_type: 'kompen_respon', task_id: int, subject_name: string, periode_semester: null} */
    private function retryImport(int $taskId): array
    {
        $task = KompenResponHubImportTask::query()->lockForUpdate()->findOrFail($taskId);
        $this->ensureImportCanBeRetried($task->status, $task->stored_path, $task->original_filename);

        $task->update($this->queuedTaskAttributes('Workbook dijadwalkan ulang untuk diproses.'));

        return [
            'task_type' => 'kompen_respon',
            'task_id' => $task->id,
            'subject_name' => $task->original_filename,
            'periode_semester' => null,
        ];
    }

    /** @return array{task_type: 'active_students', task_id: int, subject_name: string, periode_semester: null} */
    private function retryActiveStudentImport(int $taskId): array
    {
        $task = KompenResponHubActiveStudentImportTask::query()->lockForUpdate()->findOrFail($taskId);
        $this->ensureImportCanBeRetried($task->status, $task->stored_path, $task->original_filename);

        $task->update($this->queuedTaskAttributes('Daftar mahasiswa aktif dijadwalkan ulang untuk diproses.'));

        return [
            'task_type' => 'active_students',
            'task_id' => $task->id,
            'subject_name' => $task->original_filename,
            'periode_semester' => null,
        ];
    }

    /** @return array{task_type: 'export', task_id: int, subject_name: string, periode_semester: string|null} */
    private function retryExport(int $taskId, string $requestSessionId): array
    {
        $task = KompenResponHubExportTask::query()->lockForUpdate()->findOrFail($taskId);

        if ($task->status !== KompenResponHubExportTask::StatusFailed) {
            throw new \LogicException('Hanya ekspor yang gagal yang dapat dicoba kembali.');
        }

        $task->update([
            ...$this->queuedTaskAttributes('Ekspor dijadwalkan ulang untuk diproses.'),
            'request_session_id' => $requestSessionId,
            'access_token' => bin2hex(random_bytes(32)),
            'output_path' => null,
            'download_filename' => null,
            'expires_at' => now()->addHours(max(1, (int) config('kompen-respon-hub.retention.export_hours', 24))),
        ]);

        return [
            'task_type' => 'export',
            'task_id' => $task->id,
            'subject_name' => "Ekspor {$task->resource} {$task->format}",
            'periode_semester' => is_string($task->filters['periode_semester'] ?? null)
                ? $task->filters['periode_semester']
                : null,
        ];
    }

    /** @return array{status: 'queued', progress: 0, progress_message: string, error_message: null, queued_at: \DateTimeInterface, started_at: null, completed_at: null, failed_at: null} */
    private function queuedTaskAttributes(string $message): array
    {
        return [
            'status' => KompenResponHubImportTask::STATUS_QUEUED,
            'progress' => 0,
            'progress_message' => $message,
            'error_message' => null,
            'queued_at' => now(),
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
        ];
    }

    private function ensureImportCanBeRetried(string $status, string $storedPath, string $filename): void
    {
        if ($status !== KompenResponHubImportTask::STATUS_FAILED) {
            throw new \LogicException('Hanya impor yang gagal yang dapat dicoba kembali.');
        }

        if (! Storage::disk('local')->exists($storedPath)) {
            throw new \LogicException("File {$filename} sudah tidak tersedia. Unggah ulang file untuk membuat proses baru.");
        }
    }
}
