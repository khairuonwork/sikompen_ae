<?php

namespace App\Actions\KompenResponHub;

use InvalidArgumentException;
use XMLReader;
use ZipArchive;

class ValidateKompenResponHubWorkbookArchive
{
    private const REQUIRED_ENTRIES = [
        '[Content_Types].xml',
        'xl/workbook.xml',
    ];

    public function execute(string $path): void
    {
        $fileSize = filesize($path);
        $maximumUploadBytes = $this->maximumUploadKilobytes() * 1024;

        if (! is_int($fileSize) || $fileSize < 1 || $fileSize > $maximumUploadBytes) {
            throw new InvalidArgumentException('Ukuran workbook tidak valid atau melampaui batas unggahan.');
        }

        $archive = new ZipArchive;
        $result = $archive->open($path, ZipArchive::CHECKCONS);

        if ($result !== true) {
            throw new InvalidArgumentException('Workbook XLSX tidak dapat dibaca sebagai arsip yang valid.');
        }

        try {
            $this->validateEntries($archive);
            $this->rejectFormulaCells($archive);
        } finally {
            $archive->close();
        }
    }

    private function validateEntries(ZipArchive $archive): void
    {
        $maximumEntries = max(1, (int) config('kompen-respon-hub.import.max_archive_entries', 64));

        if ($archive->numFiles < 1 || $archive->numFiles > $maximumEntries) {
            throw new InvalidArgumentException('Workbook memiliki jumlah berkas internal yang tidak diizinkan.');
        }

        $requiredEntries = array_fill_keys(self::REQUIRED_ENTRIES, true);
        $maximumTotalBytes = max(1, (int) config('kompen-respon-hub.import.max_archive_uncompressed_bytes', 8 * 1024 * 1024));
        $maximumEntryBytes = max(1, (int) config('kompen-respon-hub.import.max_archive_entry_bytes', 4 * 1024 * 1024));
        $maximumCompressionRatio = max(1, (int) config('kompen-respon-hub.import.max_archive_compression_ratio', 100));
        $totalUncompressedBytes = 0;

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->statIndex($index);
            $name = $entry['name'] ?? null;
            $uncompressedSize = $entry['size'] ?? null;
            $compressedSize = $entry['comp_size'] ?? null;

            if (
                ! is_string($name)
                || ! is_int($uncompressedSize)
                || ! is_int($compressedSize)
                || $this->hasUnsafePath($name)
                || $uncompressedSize < 0
                || $compressedSize < 0
            ) {
                throw new InvalidArgumentException('Workbook memiliki struktur arsip yang tidak valid.');
            }

            if ($uncompressedSize > $maximumEntryBytes || $totalUncompressedBytes > $maximumTotalBytes - $uncompressedSize) {
                throw new InvalidArgumentException('Workbook melampaui batas data internal yang diizinkan.');
            }

            if (
                $uncompressedSize > 0
                && ($compressedSize === 0 || $uncompressedSize / $compressedSize > $maximumCompressionRatio)
            ) {
                throw new InvalidArgumentException('Workbook memiliki rasio kompresi yang tidak aman.');
            }

            $totalUncompressedBytes += $uncompressedSize;
            unset($requiredEntries[$name]);
        }

        if ($requiredEntries !== []) {
            throw new InvalidArgumentException('Workbook tidak memiliki struktur XLSX yang lengkap.');
        }
    }

    private function hasUnsafePath(string $name): bool
    {
        return $name === ''
            || strlen($name) > 255
            || str_contains($name, "\0")
            || str_contains($name, '\\')
            || str_starts_with($name, '/')
            || str_starts_with($name, '../')
            || str_contains($name, '/../');
    }

    private function rejectFormulaCells(ZipArchive $archive): void
    {
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->statIndex($index);
            $name = $entry['name'] ?? null;

            if (! is_string($name) || preg_match('#^xl/worksheets/[^/]+\.xml$#', $name) !== 1) {
                continue;
            }

            $worksheetXml = $archive->getFromIndex($index);

            if (! is_string($worksheetXml)) {
                throw new InvalidArgumentException('Workbook memiliki worksheet yang tidak dapat dibaca.');
            }

            $reader = new XMLReader;
            if (! $reader->XML($worksheetXml, null, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new InvalidArgumentException('Workbook memiliki struktur worksheet yang tidak valid.');
            }

            try {
                while ($reader->read()) {
                    if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'f') {
                        throw new InvalidArgumentException('Workbook tidak boleh berisi formula. Gunakan nilai statis pada seluruh cell data.');
                    }
                }
            } finally {
                $reader->close();
            }
        }
    }

    private function maximumUploadKilobytes(): int
    {
        return max(1, (int) config('kompen-respon-hub.import.max_upload_kilobytes', 5120));
    }
}
