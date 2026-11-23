<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\KompenResponHub\SikompenAdminAccess;
use App\Actions\KompenResponHub\ValidateKompenResponHubWorkbookArchive;
use App\Http\Requests\DestroyKompenResponHubImportRequest;
use App\Http\Requests\PermanentlyDestroyKompenResponHubImportRequest;
use App\Http\Requests\RestoreKompenResponHubImportRequest;
use App\Http\Requests\StoreKompenResponHubImportRequest;
use App\Http\Requests\UpdateKompenResponHubImportRequest;
use App\Jobs\ProcessKompenResponHubImport;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubImportTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KompenResponHubImportController extends Controller
{
    public function downloadTemplate(): BinaryFileResponse
    {
        $path = storage_path('app/private/kompen-respon-hub/Template_Impor_Kompen_Respon_Hub.xlsx');

        abort_unless(file_exists($path), 404, 'Template impor tidak ditemukan.');

        return response()->download($path, 'Template_Impor_Kompen_Respon_Hub.xlsx');
    }

    public function downloadUploadedWorkbook(KompenResponHubImport $import): StreamedResponse
    {
        abort_unless(
            str_starts_with($import->stored_path, 'kompen-respon-hub/imports/')
                && Storage::disk('local')->exists($import->stored_path),
            404,
            'File unggahan tidak tersedia.',
        );

        $downloadFilename = $this->safeDownloadFilename($import);

        return Storage::disk('local')->download(
            $import->stored_path,
            $downloadFilename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function update(
        UpdateKompenResponHubImportRequest $request,
        SikompenAdminAccess $access,
        RecordKompenResponHubActivity $activity,
        KompenResponHubImport $import,
    ): RedirectResponse {
        $displayFilename = $request->string('display_filename')->toString();
        $currentFilename = $import->display_filename ?? $import->original_filename;

        if ($displayFilename === $currentFilename) {
            return to_route('admin.kompen-respon.index', ['tab' => 'files'])
                ->with('success', 'Nama file tidak berubah.');
        }

        $actor = $access->actor($request);
        $admin = $this->adminForActor($actor['id']);

        $import->update(['display_filename' => $displayFilename]);

        KompenResponHubImportAuditLog::create([
            'event_type' => KompenResponHubImportAuditLog::EVENT_RENAME,
            'source_import_id' => $import->id,
            'actor_name' => $admin instanceof KompenResponHubAdmin ? $admin->username : $actor['username'],
            'actor_username' => $actor['username'],
            'periode_semester' => $import->periode_semester,
            'original_filename' => $displayFilename,
            'class_count' => $import->class_count,
            'student_count' => $import->student_count,
            'detail_count' => $import->detail_count,
            'metadata' => ['previous_display_filename' => $currentFilename],
            'occurred_at' => now(),
        ]);

        $activity->execute(
            'import.renamed',
            'import',
            (string) $import->id,
            $admin,
            $request,
            period: $import->periode_semester,
            reason: 'Nama tampilan workbook diperbarui.',
            beforeState: ['display_filename' => $currentFilename],
            afterState: ['display_filename' => $displayFilename],
        );

        return to_route('admin.kompen-respon.index', ['tab' => 'files'])
            ->with('success', "Nama file diubah menjadi {$displayFilename}.");
    }

    public function destroy(
        DestroyKompenResponHubImportRequest $request,
        SikompenAdminAccess $access,
        RecordKompenResponHubActivity $activity,
        KompenResponHubImport $import,
    ): RedirectResponse {
        $actor = $access->actor($request);
        $admin = $this->adminForActor($actor['id']);

        /** @var array{id: int, display_filename: string, original_filename: string, periode_semester: string, class_count: int, student_count: int, detail_count: int} $deletedImport */
        $deletedImport = DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($import, $admin, $actor): array {
                $lockedImport = KompenResponHubImport::query()
                    ->lockForUpdate()
                    ->findOrFail($import->id);

                abort_if(
                    $lockedImport->activeReference()->lockForUpdate()->exists(),
                    422,
                    'Workbook yang sedang digunakan tidak dapat dihapus. Pilih versi lain untuk periode ini terlebih dahulu.',
                );
                $snapshot = [
                    'id' => $lockedImport->id,
                    'display_filename' => $lockedImport->display_filename ?? $lockedImport->original_filename,
                    'original_filename' => $lockedImport->original_filename,
                    'periode_semester' => $lockedImport->periode_semester,
                    'class_count' => $lockedImport->class_count,
                    'student_count' => $lockedImport->student_count,
                    'detail_count' => $lockedImport->detail_count,
                ];

                KompenResponHubImportAuditLog::create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_DELETE,
                    'source_import_id' => $lockedImport->id,
                    'actor_name' => $admin instanceof KompenResponHubAdmin ? $admin->username : $actor['username'],
                    'actor_username' => $actor['username'],
                    'periode_semester' => $lockedImport->periode_semester,
                    'original_filename' => $snapshot['display_filename'],
                    'class_count' => $lockedImport->class_count,
                    'student_count' => $lockedImport->student_count,
                    'detail_count' => $lockedImport->detail_count,
                    'metadata' => [
                        'original_filename' => $lockedImport->original_filename,
                        'moved_to_recycle_bin' => true,
                    ],
                    'occurred_at' => now(),
                ]);

                $lockedImport->delete();

                return $snapshot;
            });

        $activity->execute(
            'import.deleted',
            'import',
            (string) $deletedImport['id'],
            $admin,
            $request,
            period: $deletedImport['periode_semester'],
            reason: 'Versi workbook dipindahkan ke recycle bin.',
            beforeState: [
                'display_filename' => $deletedImport['display_filename'],
                'original_filename' => $deletedImport['original_filename'],
            ],
        );

        return to_route('admin.kompen-respon.index', ['tab' => 'files'])
            ->with('success', "Versi {$deletedImport['display_filename']} dipindahkan ke recycle bin. File dapat dipulihkan sebelum masa retensi berakhir.");
    }

    public function restore(
        RestoreKompenResponHubImportRequest $request,
        SikompenAdminAccess $access,
        RecordKompenResponHubActivity $activity,
        string $import,
    ): RedirectResponse {
        $actor = $access->actor($request);
        $admin = $this->adminForActor($actor['id']);

        /** @var KompenResponHubImport $restoredImport */
        $restoredImport = DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($actor, $admin, $import): KompenResponHubImport {
                $trashedImport = KompenResponHubImport::withTrashed()
                    ->lockForUpdate()
                    ->findOrFail((int) $import);

                abort_unless($trashedImport->trashed(), 422, 'Versi workbook ini tidak berada di recycle bin.');
                abort_unless(Storage::disk('local')->exists($trashedImport->stored_path), 422, 'File workbook sudah tidak tersedia dan tidak dapat dipulihkan.');

                $trashedImport->restore();

                KompenResponHubImportAuditLog::create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_RESTORE,
                    'source_import_id' => $trashedImport->id,
                    'actor_name' => $admin instanceof KompenResponHubAdmin ? $admin->username : $actor['username'],
                    'actor_username' => $actor['username'],
                    'periode_semester' => $trashedImport->periode_semester,
                    'original_filename' => $trashedImport->display_filename ?? $trashedImport->original_filename,
                    'class_count' => $trashedImport->class_count,
                    'student_count' => $trashedImport->student_count,
                    'detail_count' => $trashedImport->detail_count,
                    'metadata' => ['restored_from_recycle_bin' => true],
                    'occurred_at' => now(),
                ]);

                return $trashedImport;
            });

        $activity->execute(
            'import.restored_from_recycle_bin',
            'import',
            (string) $restoredImport->id,
            $admin,
            $request,
            period: $restoredImport->periode_semester,
            reason: 'Versi workbook dipulihkan dari recycle bin.',
            subjectName: $restoredImport->display_filename ?? $restoredImport->original_filename,
        );

        return to_route('admin.kompen-respon.index', ['tab' => 'files'])
            ->with('success', "Versi {$restoredImport->display_filename} dipulihkan dari recycle bin.");
    }

    public function forceDestroy(
        PermanentlyDestroyKompenResponHubImportRequest $request,
        SikompenAdminAccess $access,
        RecordKompenResponHubActivity $activity,
        string $import,
    ): RedirectResponse {
        $actor = $access->actor($request);
        $admin = $this->adminForActor($actor['id']);

        /** @var array{id: int, display_filename: string, original_filename: string, periode_semester: string, class_count: int, student_count: int, detail_count: int} $deletedImport */
        $deletedImport = DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($actor, $admin, $import): array {
                $trashedImport = KompenResponHubImport::onlyTrashed()
                    ->lockForUpdate()
                    ->findOrFail((int) $import);

                $snapshot = [
                    'id' => $trashedImport->id,
                    'display_filename' => $trashedImport->display_filename ?? $trashedImport->original_filename,
                    'original_filename' => $trashedImport->original_filename,
                    'periode_semester' => $trashedImport->periode_semester,
                    'class_count' => $trashedImport->class_count,
                    'student_count' => $trashedImport->student_count,
                    'detail_count' => $trashedImport->detail_count,
                ];

                if (str_starts_with($trashedImport->stored_path, 'kompen-respon-hub/imports/')
                    && Storage::disk('local')->exists($trashedImport->stored_path)) {
                    abort_unless(
                        Storage::disk('local')->delete($trashedImport->stored_path),
                        500,
                        'File workbook tidak dapat dihapus dari penyimpanan.',
                    );
                }

                KompenResponHubImportAuditLog::create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_PURGE,
                    'source_import_id' => $trashedImport->id,
                    'actor_name' => $admin instanceof KompenResponHubAdmin ? $admin->username : $actor['username'],
                    'actor_username' => $actor['username'],
                    'periode_semester' => $trashedImport->periode_semester,
                    'original_filename' => $snapshot['display_filename'],
                    'class_count' => $trashedImport->class_count,
                    'student_count' => $trashedImport->student_count,
                    'detail_count' => $trashedImport->detail_count,
                    'metadata' => [
                        'original_filename' => $trashedImport->original_filename,
                        'permanently_deleted_from_recycle_bin' => true,
                    ],
                    'occurred_at' => now(),
                ]);

                $trashedImport->forceDelete();

                return $snapshot;
            });

        $activity->execute(
            'import.permanently_deleted',
            'import',
            (string) $deletedImport['id'],
            $admin,
            $request,
            period: $deletedImport['periode_semester'],
            reason: 'Workbook dan data hasil impor dihapus permanen dari recycle bin.',
            beforeState: [
                'display_filename' => $deletedImport['display_filename'],
                'original_filename' => $deletedImport['original_filename'],
                'class_count' => $deletedImport['class_count'],
                'student_count' => $deletedImport['student_count'],
                'detail_count' => $deletedImport['detail_count'],
            ],
            subjectName: $deletedImport['display_filename'],
        );

        return to_route('admin.kompen-respon.index', ['tab' => 'files'])
            ->with('success', "Versi {$deletedImport['display_filename']} dihapus permanen dari recycle bin.");
    }

    public function store(
        StoreKompenResponHubImportRequest $request,
        SikompenAdminAccess $access,
        ValidateKompenResponHubWorkbookArchive $archiveValidator,
    ): RedirectResponse {
        $actor = $access->actor($request);
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'Workbook wajib diunggah dalam format XLSX.',
            ]);
        }

        try {
            $archiveValidator->execute($file->getPathname());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        $fileHash = hash_file('sha256', $file->getPathname());
        if (! is_string($fileHash)) {
            throw ValidationException::withMessages([
                'file' => 'Workbook tidak dapat diperiksa. Coba unggah kembali.',
            ]);
        }

        $lock = Cache::lock("sikompen:kompen-respon-import:{$fileHash}", 15);
        if (! $lock->get()) {
            return to_route('admin.kompen-respon.index', ['tab' => 'upload'])
                ->with('error', 'Workbook identik sedang diperiksa atau diproses. Tunggu proses sebelumnya selesai.');
        }

        try {
            $duplicateMessage = $this->duplicateImportMessage($fileHash);
            if ($duplicateMessage !== null) {
                return to_route('admin.kompen-respon.index', ['tab' => 'upload'])
                    ->with('error', $duplicateMessage);
            }

            $storedPath = $file->store('kompen-respon-hub/imports');

            if ($storedPath === false) {
                throw ValidationException::withMessages([
                    'file' => 'Workbook tidak dapat disimpan. Coba unggah kembali.',
                ]);
            }

            $importTask = KompenResponHubImportTask::create([
                'uploaded_by_admin_id' => $actor['id'],
                'uploader_name' => $request->string('uploader_name')->trim()->toString(),
                'uploader_username' => $actor['username'],
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'file_hash' => $fileHash,
                'status' => KompenResponHubImportTask::STATUS_QUEUED,
                'progress' => 0,
                'progress_message' => 'Workbook diterima dan menunggu diproses.',
                'queued_at' => now(),
            ]);
        } finally {
            $lock->release();
        }

        ProcessKompenResponHubImport::dispatch($importTask->id)
            ->onQueue(config('kompen-respon-hub.queue.import'));

        return to_route('admin.kompen-respon.index', ['tab' => 'upload'])
            ->with(
                'success',
                'Workbook diterima. Proses impor berjalan di latar belakang dan tetap berlanjut saat Anda membuka tabel lain.',
            );
    }

    private function safeDownloadFilename(KompenResponHubImport $import): string
    {
        $filename = $import->display_filename ?? $import->original_filename;

        if (preg_match('/^[\pL\pN][\pL\pN ._()\-]*\.xlsx$/u', $filename) !== 1) {
            return "sikompen-import-{$import->id}.xlsx";
        }

        return $filename;
    }

    private function adminForActor(int|string|null $actorId): ?KompenResponHubAdmin
    {
        return is_int($actorId)
            ? KompenResponHubAdmin::query()->find($actorId)
            : null;
    }

    private function duplicateImportMessage(string $fileHash): ?string
    {
        $activeTask = KompenResponHubImportTask::query()
            ->where('file_hash', $fileHash)
            ->whereIn('status', [
                KompenResponHubImportTask::STATUS_QUEUED,
                KompenResponHubImportTask::STATUS_PROCESSING,
            ])
            ->latest('id')
            ->first();

        if ($activeTask instanceof KompenResponHubImportTask) {
            return "Workbook identik sedang diproses sebagai {$activeTask->original_filename}. Tunggu proses tersebut selesai.";
        }

        $existingImport = KompenResponHubImport::query()
            ->where('file_hash', $fileHash)
            ->latest('id')
            ->first();

        if ($existingImport instanceof KompenResponHubImport) {
            $filename = $existingImport->display_filename ?? $existingImport->original_filename;

            return "Workbook identik sudah tersimpan sebagai {$filename}. Gunakan versi yang ada di List File.";
        }

        $trashedImport = KompenResponHubImport::onlyTrashed()
            ->where('file_hash', $fileHash)
            ->latest('id')
            ->first();

        if ($trashedImport instanceof KompenResponHubImport) {
            $filename = $trashedImport->display_filename ?? $trashedImport->original_filename;

            return "Workbook identik sudah berada di Recycle Bin sebagai {$filename}. Pulihkan versi tersebut, bukan mengunggah ulang.";
        }

        return null;
    }
}
