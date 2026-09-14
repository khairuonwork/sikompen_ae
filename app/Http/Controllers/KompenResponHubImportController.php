<?php

namespace App\Http\Controllers;

use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use App\Http\Requests\StoreKompenResponHubImportRequest;
use App\Jobs\ProcessKompenResponHubImport;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportTask;
use Illuminate\Http\RedirectResponse;
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

        return Storage::disk('local')->download(
            $import->stored_path,
            "sikompen-import-{$import->id}.xlsx",
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
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
}
