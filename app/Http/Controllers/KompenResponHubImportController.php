<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use App\Http\Requests\DestroyKompenResponHubImportRequest;
use App\Http\Requests\StoreKompenResponHubImportRequest;
use App\Http\Requests\UpdateKompenResponHubImportRequest;
use App\Jobs\ProcessKompenResponHubImport;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubImportTask;
use App\Models\KompenResponHubStudent;
use Illuminate\Http\RedirectResponse;
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
        SiAdminProxyAccess $access,
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
            'actor_name' => $admin?->email ?? $actor['email'],
            'actor_email' => $actor['email'],
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
        SiAdminProxyAccess $access,
        RecordKompenResponHubActivity $activity,
        KompenResponHubImport $import,
    ): RedirectResponse {
        $actor = $access->actor($request);
        $admin = $this->adminForActor($actor['id']);

        /** @var array{id: int, display_filename: string, original_filename: string, stored_path: string, periode_semester: string, class_count: int, student_count: int, detail_count: int} $deletedImport */
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
                abort_if(
                    KompenResponHubStudent::query()
                        ->where('kompen_respon_hub_import_id', $lockedImport->id)
                        ->lockForUpdate()
                        ->exists(),
                    422,
                    'Workbook ini masih menjadi sumber data mahasiswa dan tidak dapat dihapus.',
                );

                $snapshot = [
                    'id' => $lockedImport->id,
                    'display_filename' => $lockedImport->display_filename ?? $lockedImport->original_filename,
                    'original_filename' => $lockedImport->original_filename,
                    'stored_path' => $lockedImport->stored_path,
                    'periode_semester' => $lockedImport->periode_semester,
                    'class_count' => $lockedImport->class_count,
                    'student_count' => $lockedImport->student_count,
                    'detail_count' => $lockedImport->detail_count,
                ];

                KompenResponHubImportAuditLog::create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_DELETE,
                    'source_import_id' => $lockedImport->id,
                    'actor_name' => $admin?->email ?? $actor['email'],
                    'actor_email' => $actor['email'],
                    'periode_semester' => $lockedImport->periode_semester,
                    'original_filename' => $snapshot['display_filename'],
                    'class_count' => $lockedImport->class_count,
                    'student_count' => $lockedImport->student_count,
                    'detail_count' => $lockedImport->detail_count,
                    'metadata' => ['original_filename' => $lockedImport->original_filename],
                    'occurred_at' => now(),
                ]);

                $lockedImport->delete();

                return $snapshot;
            });

        if (str_starts_with($deletedImport['stored_path'], 'kompen-respon-hub/imports/')) {
            Storage::disk('local')->delete($deletedImport['stored_path']);
        }

        $activity->execute(
            'import.deleted',
            'import',
            (string) $deletedImport['id'],
            $admin,
            $request,
            period: $deletedImport['periode_semester'],
            reason: 'Versi workbook dihapus dari List File.',
            beforeState: [
                'display_filename' => $deletedImport['display_filename'],
                'original_filename' => $deletedImport['original_filename'],
            ],
        );

        return to_route('admin.kompen-respon.index', ['tab' => 'files'])
            ->with('success', "Versi {$deletedImport['display_filename']} berhasil dihapus. Riwayat audit tetap tersimpan.");
    }

    public function store(
        StoreKompenResponHubImportRequest $request,
        SiAdminProxyAccess $access,
    ): RedirectResponse {
        $actor = $access->actor($request);
        $file = $request->file('file');
        $storedPath = $file->store('kompen-respon-hub/imports');

        if ($storedPath === false) {
            throw ValidationException::withMessages([
                'file' => 'Workbook tidak dapat disimpan. Coba unggah kembali.',
            ]);
        }

        $fullPath = Storage::disk('local')->path($storedPath);

        $importTask = KompenResponHubImportTask::create([
            'uploaded_by_admin_id' => $actor['id'],
            'uploader_name' => $request->string('uploader_name')->trim()->toString(),
            'uploader_email' => $actor['email'],
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'file_hash' => hash_file('sha256', $fullPath),
            'status' => KompenResponHubImportTask::STATUS_QUEUED,
            'progress' => 0,
            'progress_message' => 'Workbook diterima dan menunggu diproses.',
            'queued_at' => now(),
        ]);

        ProcessKompenResponHubImport::dispatch($importTask->id);

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
}
