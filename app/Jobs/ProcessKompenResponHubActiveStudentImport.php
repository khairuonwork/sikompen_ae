<?php

namespace App\Jobs;

use App\Actions\KompenResponHub\ImportKompenResponHubActiveStudents;
use App\Actions\KompenResponHub\ParseKompenResponHubActiveStudentWorkbook;
use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\KompenResponHub\ValidateKompenResponHubWorkbookArchive;
use App\Models\KompenResponHubActiveStudentImportTask;
use App\Models\KompenResponHubAdmin;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessKompenResponHubActiveStudentImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $importTaskId) {}

    public function handle(
        ParseKompenResponHubActiveStudentWorkbook $parser,
        ImportKompenResponHubActiveStudents $importer,
        RecordKompenResponHubActivity $activity,
        ValidateKompenResponHubWorkbookArchive $archiveValidator,
    ): void {
        $task = KompenResponHubActiveStudentImportTask::query()->find($this->importTaskId);

        if ($task === null || ! $task->isActive()) {
            return;
        }

        $this->updateProgress(
            $task,
            KompenResponHubActiveStudentImportTask::StatusProcessing,
            15,
            'Membaca daftar mahasiswa aktif.',
            ['started_at' => now()],
        );

        try {
            $path = Storage::disk('local')->path($task->stored_path);
            $archiveValidator->execute($path);
            $payload = $parser->execute($path);
        } catch (\InvalidArgumentException $exception) {
            $this->markAsFailed($task, $exception->getMessage(), $activity);

            return;
        } catch (Throwable) {
            $this->markAsFailed(
                $task,
                'Daftar mahasiswa aktif tidak dapat dibaca.',
                $activity,
            );

            return;
        }

        if ($payload['errors'] !== []) {
            $this->markAsFailed($task, $this->validationErrorMessage($payload['errors']), $activity);

            return;
        }

        $this->updateProgress(
            $task,
            KompenResponHubActiveStudentImportTask::StatusProcessing,
            60,
            'Memvalidasi periode, kelas, NIM, dan nama mahasiswa aktif.',
        );

        $this->updateProgress(
            $task,
            KompenResponHubActiveStudentImportTask::StatusProcessing,
            80,
            'Memperbarui daftar mahasiswa aktif.',
        );

        $result = $importer->execute(
            $payload,
            $task->original_filename,
            $task->stored_path,
            $task->file_hash,
            (int) $task->uploaded_by_admin_id,
            $task->uploader_name,
            (string) $task->uploader_email,
        );

        $activity->execute(
            eventType: 'active_students.imported',
            subjectType: 'active_student_import',
            subjectReference: (string) $result['import_id'],
            actor: KompenResponHubAdmin::query()->find($task->uploaded_by_admin_id),
            reason: 'Versi daftar mahasiswa aktif ditambahkan dari file unggahan.',
            period: $result['periode_semester'],
            metadata: [
                'student_count' => $result['student_count'],
                'class_count' => $result['class_count'],
                'original_filename' => $task->original_filename,
                'periode_semester' => $result['periode_semester'],
            ],
            subjectName: $task->original_filename,
        );

        $this->updateProgress(
            $task,
            KompenResponHubActiveStudentImportTask::StatusCompleted,
            100,
            "Selesai: {$result['student_count']} mahasiswa aktif pada {$result['class_count']} kelas untuk {$result['periode_semester']} siap digunakan.",
            [
                'sikompen_active_student_import_id' => $result['import_id'],
                'completed_at' => now(),
            ],
        );
    }

    public function failed(?Throwable $exception): void
    {
        $task = KompenResponHubActiveStudentImportTask::query()->find($this->importTaskId);

        if ($task === null || $task->status === KompenResponHubActiveStudentImportTask::StatusCompleted) {
            return;
        }

        $this->markAsFailed(
            $task,
            'Proses impor daftar mahasiswa aktif berhenti karena terjadi kendala pada server.',
            app(RecordKompenResponHubActivity::class),
        );
    }

    /**
     * @param  array{started_at?: CarbonInterface, completed_at?: CarbonInterface, sikompen_active_student_import_id?: int, failed_at?: CarbonInterface}  $additionalAttributes
     */
    private function updateProgress(
        KompenResponHubActiveStudentImportTask $task,
        string $status,
        int $progress,
        string $message,
        array $additionalAttributes = [],
    ): void {
        $task->update([
            'status' => $status,
            'progress' => $progress,
            'progress_message' => $message,
            ...$additionalAttributes,
        ]);
    }

    /** @param list<array{location: string, message: string}> $errors */
    private function validationErrorMessage(array $errors): string
    {
        return 'Daftar mahasiswa aktif perlu diperbaiki. '.collect($errors)
            ->take(5)
            ->map(fn (array $error): string => "{$error['location']}: {$error['message']}")
            ->implode(' ');
    }

    private function markAsFailed(
        KompenResponHubActiveStudentImportTask $task,
        string $message,
        RecordKompenResponHubActivity $activity,
    ): void {
        if (! $task->isActive()) {
            return;
        }

        $sourceWorkbookRemoved = $this->deleteSourceWorkbook($task->stored_path);
        $failureMessage = $this->failureMessage($message);

        $this->updateProgress(
            $task,
            KompenResponHubActiveStudentImportTask::StatusFailed,
            100,
            'Impor daftar mahasiswa aktif gagal. Perbaiki file lalu unggah ulang.',
            [
                'error_message' => $failureMessage,
                'failed_at' => now(),
            ],
        );

        $activity->execute(
            'active_students.import_failed',
            'active_student_import_task',
            (string) $task->id,
            KompenResponHubAdmin::query()->find($task->uploaded_by_admin_id),
            reason: $failureMessage,
            metadata: [
                'original_filename' => $task->original_filename,
                'source_workbook_removed' => $sourceWorkbookRemoved,
            ],
            subjectName: $task->original_filename,
        );
    }

    private function failureMessage(string $message): string
    {
        return "{$message} File unggahan dibersihkan demi konsistensi data. Perbaiki daftar lalu unggah ulang.";
    }

    private function deleteSourceWorkbook(string $storedPath): bool
    {
        if (! str_starts_with($storedPath, 'kompen-respon-hub/imports/active-students/')) {
            return false;
        }

        try {
            return Storage::disk('local')->delete($storedPath);
        } catch (Throwable) {
            return false;
        }
    }
}
