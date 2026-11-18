<?php

namespace App\Http\Controllers;

use App\Models\KompenResponHubWarningLetterArtifact;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KompenResponHubWarningLetterArtifactController extends Controller
{
    public function download(KompenResponHubWarningLetterArtifact $artifact): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_unless($disk->exists($artifact->stored_path), 404, 'Artifact Surat Peringatan tidak ditemukan pada penyimpanan.');
        $fileHash = hash_file('sha256', $disk->path($artifact->stored_path));
        abort_unless(
            is_string($fileHash) && hash_equals($artifact->file_hash, $fileHash),
            409,
            'Artifact Surat Peringatan tidak lolos pemeriksaan integritas.',
        );

        return $disk->download($artifact->stored_path, $artifact->display_filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
