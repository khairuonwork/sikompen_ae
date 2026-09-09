<?php

namespace App\Jobs;

use App\Actions\KompenResponHub\ImportKompenResponHubWorkbook;
use App\Actions\KompenResponHub\ParseKompenResponHubWorkbook;
use App\Models\KompenResponHubImportTask;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessKompenResponHubImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $importTaskId) {}

    public function handle(
        ParseKompenResponHubWorkbook $parser,
        ImportKompenResponHubWorkbook $importer,
    ): void {
        $importTask = KompenResponHubImportTask::query()->find($this->importTaskId);

        if ($importTask === null || ! $importTask->isActive()) {
            return;
        }

        $this->updateProgress(
            $importTask,
            KompenResponHubImportTask::STATUS_PROCESSING,
            15,
            'Membaca workbook.',
            ['started_at' => now()],
        );

        $fullPath = Storage::disk('local')->path($importTask->stored_path);

        try {
            $payload = $parser->execute($fullPath);
        } catch (Throwable) {
            $this->markAsFailed(
                $importTask,
                'Workbook tidak dapat dibaca. Simpan ulang sebagai XLSX dari template lalu unggah kembali.',
            );

            return;
        }

        if ($payload['errors'] !== []) {
            $this->markAsFailed($importTask, $this->validationErrorMessage($payload['errors']));

            return;
        }

        $this->updateProgress(
            $importTask,
            KompenResponHubImportTask::STATUS_PROCESSING,
            55,
            'Memvalidasi data mahasiswa dan detail kompen.',
        );

        $this->updateProgress(
            $importTask,
            KompenResponHubImportTask::STATUS_PROCESSING,
            75,
            'Menyimpan data ke database.',
        );

        $result = $importer->execute(
            $payload,
            $importTask->original_filename,
            $importTask->stored_path,
            $importTask->file_hash,
            (int) $importTask->uploaded_by_admin_id,
            $importTask->uploader_name,
            $importTask->uploader_email,
        );

        $this->updateProgress(
            $importTask,
            KompenResponHubImportTask::STATUS_COMPLETED,
            100,
            "Selesai: {$result['student_count']} mahasiswa dan {$result['detail_count']} detail kompen disimpan.",
            [
                'kompen_respon_hub_import_id' => $result['import_id'],
                'completed_at' => now(),
            ],
        );
    }

    public function failed(?Throwable $exception): void
    {
        $importTask = KompenResponHubImportTask::query()->find($this->importTaskId);

        if ($importTask === null || $importTask->status === KompenResponHubImportTask::STATUS_COMPLETED) {
            return;
        }

        $this->markAsFailed(
            $importTask,
            'Proses impor berhenti karena terjadi kendala pada server. Silakan unggah kembali atau hubungi administrator.',
        );
    }

    /**
     * @param  array{started_at?: CarbonInterface, completed_at?: CarbonInterface, kompen_respon_hub_import_id?: int}  $additionalAttributes
     */
    private function updateProgress(
        KompenResponHubImportTask $importTask,
        string $status,
        int $progress,
        string $message,
        array $additionalAttributes = [],
    ): void {
        $importTask->update([
            'status' => $status,
            'progress' => $progress,
            'progress_message' => $message,
            ...$additionalAttributes,
        ]);
    }

    /** @param list<array{location: string, message: string}> $errors */
    private function validationErrorMessage(array $errors): string
    {
        $messages = collect($errors)
            ->take(5)
            ->map(fn (array $error): string => "{$error['location']}: {$error['message']}")
            ->implode(' ');

        return "Workbook perlu diperbaiki. {$messages}";
    }

    private function markAsFailed(KompenResponHubImportTask $importTask, string $message): void
    {
        $this->updateProgress(
            $importTask,
            KompenResponHubImportTask::STATUS_FAILED,
            100,
            'Impor gagal. Periksa keterangan di bawah.',
            [
                'error_message' => $message,
                'failed_at' => now(),
            ],
        );

        Storage::disk('local')->delete($importTask->stored_path);
    }
}
