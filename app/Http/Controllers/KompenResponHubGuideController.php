<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KompenResponHubGuideController extends Controller
{
    public function student(): BinaryFileResponse
    {
        return $this->displayGuide('panduan-mahasiswa.pdf');
    }

    public function admin(): BinaryFileResponse
    {
        return $this->displayGuide('panduan-admin.pdf');
    }

    private function displayGuide(string $filename): BinaryFileResponse
    {
        $path = base_path("docs/{$filename}");

        abort_unless(is_file($path), 404, 'Panduan belum tersedia.');

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
