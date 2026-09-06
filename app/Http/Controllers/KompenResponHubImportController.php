<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\ImportKompenResponHubWorkbook;
use App\Actions\KompenResponHub\ParseKompenResponHubWorkbook;
use App\Http\Requests\StoreKompenResponHubImportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class KompenResponHubImportController extends Controller
{
    public function downloadTemplate(): BinaryFileResponse
    {
        $path = storage_path('app/private/kompen-respon-hub/Template_Impor_Kompen_Respon_Hub.xlsx');

        abort_unless(file_exists($path), 404, 'Template impor tidak ditemukan.');

        return response()->download($path, 'Template_Impor_Kompen_Respon_Hub.xlsx');
    }

    public function store(
        StoreKompenResponHubImportRequest $request,
        ParseKompenResponHubWorkbook $parser,
        ImportKompenResponHubWorkbook $importer,
    ): RedirectResponse {
        $file = $request->file('file');
        $storedPath = $file->store('kompen-respon-hub/imports');
        $fullPath = Storage::disk('local')->path($storedPath);

        try {
            $payload = $parser->execute($fullPath);
        } catch (Throwable) {
            Storage::disk('local')->delete($storedPath);

            throw ValidationException::withMessages([
                'file' => 'Workbook tidak dapat dibaca. Simpan ulang sebagai XLSX dari template lalu unggah kembali.',
            ]);
        }

        if ($payload['errors'] !== []) {
            Storage::disk('local')->delete($storedPath);

            throw ValidationException::withMessages([
                'file' => collect($payload['errors'])
                    ->map(fn (array $error): string => "{$error['location']}: {$error['message']}")
                    ->all(),
            ]);
        }

        $result = $importer->execute(
            $payload,
            $file->getClientOriginalName(),
            $storedPath,
            hash_file('sha256', $fullPath),
        );

        return to_route('kompen-respon-hub.index')
            ->with('success', "Impor selesai: {$result['student_count']} mahasiswa dan {$result['detail_count']} detail kompen disimpan.");
    }
}
