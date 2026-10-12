<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\ValidateKompenResponHubWorkbookArchive;
use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use App\Http\Requests\StoreKompenResponHubActiveStudentImportRequest;
use App\Http\Resources\KompenResponHubActiveStudentImportTaskResource;
use App\Jobs\ProcessKompenResponHubActiveStudentImport;
use App\Models\KompenResponHubActiveStudentImport;
use App\Models\KompenResponHubActiveStudentImportTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KompenResponHubActiveStudentImportController extends Controller
{
    public function downloadTemplate(): BinaryFileResponse
    {
        $path = storage_path('app/private/kompen-respon-hub/Template_Daftar_Mahasiswa_Aktif_Sikompen.xlsx');

        abort_unless(file_exists($path), 404, 'Template daftar mahasiswa aktif tidak ditemukan.');

        return response()->download($path, 'Template_Daftar_Mahasiswa_Aktif_Sikompen.xlsx');
    }

    public function downloadUploadedWorkbook(KompenResponHubActiveStudentImport $import): StreamedResponse
    {
        abort_unless(
            str_starts_with($import->stored_path, 'kompen-respon-hub/active-student-imports/')
                && Storage::disk('local')->exists($import->stored_path),
            404,
            'File unggahan tidak tersedia.',
        );

        return Storage::disk('local')->download(
            $import->stored_path,
            $this->safeDownloadFilename($import),
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function store(
        StoreKompenResponHubActiveStudentImportRequest $request,
        SiAdminProxyAccess $access,
        ValidateKompenResponHubWorkbookArchive $archiveValidator,
    ): RedirectResponse {
        $actor = $access->actor($request);
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'Daftar mahasiswa aktif wajib diunggah dalam format XLSX.',
            ]);
        }

        try {
            $archiveValidator->execute($file->getPathname());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        $storedPath = $file->store('kompen-respon-hub/active-student-imports');

        if ($storedPath === false) {
            throw ValidationException::withMessages([
                'file' => 'Daftar mahasiswa aktif tidak dapat disimpan. Coba unggah kembali.',
            ]);
        }

        $task = KompenResponHubActiveStudentImportTask::create([
            'uploaded_by_admin_id' => $actor['id'],
            'uploader_name' => $request->string('uploader_name')->trim()->toString(),
            'uploader_email' => $actor['email'],
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'file_hash' => hash_file('sha256', Storage::disk('local')->path($storedPath)),
            'status' => KompenResponHubActiveStudentImportTask::StatusQueued,
            'progress' => 0,
            'progress_message' => 'Daftar mahasiswa aktif diterima dan menunggu diproses.',
            'queued_at' => now(),
        ]);

        ProcessKompenResponHubActiveStudentImport::dispatch($task->id);

        return to_route('admin.kompen-respon.index', ['tab' => 'upload'])
            ->with('success', 'Daftar mahasiswa aktif diterima. Proses impor berjalan di latar belakang.');
    }

    public function showTask(KompenResponHubActiveStudentImportTask $task): JsonResponse
    {
        return (new KompenResponHubActiveStudentImportTaskResource($task))->response();
    }

    private function safeDownloadFilename(KompenResponHubActiveStudentImport $import): string
    {
        if (preg_match('/^[\pL\pN][\pL\pN ._()\-]*\.xlsx$/u', $import->original_filename) !== 1) {
            return "sikompen-daftar-mahasiswa-aktif-{$import->id}.xlsx";
        }

        return $import->original_filename;
    }
}
