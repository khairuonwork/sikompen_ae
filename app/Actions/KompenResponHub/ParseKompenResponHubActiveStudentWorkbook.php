<?php

namespace App\Actions\KompenResponHub;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ParseKompenResponHubActiveStudentWorkbook
{
    private const REQUIRED_SHEETS = ['Petunjuk', 'Mahasiswa Aktif'];

    private const HEADERS = ['NIM', 'NAMA MAHASISWA', 'KELAS'];

    /**
     * @return array{errors: list<array{location: string, message: string}>, students: list<array{nim: string, nama_mahasiswa: string, kelas: string}>, preview: array{student_count: int, class_count: int}}
     */
    public function execute(string $path): array
    {
        $workbook = IOFactory::load($path);
        $sheets = [];

        foreach ($workbook->getWorksheetIterator() as $sheet) {
            $sheets[$sheet->getTitle()] = $sheet;
        }

        $errors = $this->validateStructure($sheets);

        if ($errors !== []) {
            return $this->result($errors, []);
        }

        $students = $this->parseStudents($sheets['Mahasiswa Aktif'], $errors);

        return $this->result($errors, $students);
    }

    /**
     * @param  array<string, Worksheet>  $sheets
     * @return list<array{location: string, message: string}>
     */
    private function validateStructure(array $sheets): array
    {
        $errors = [];

        foreach (array_keys($sheets) as $sheetName) {
            if (! in_array($sheetName, self::REQUIRED_SHEETS, true)) {
                $errors[] = [
                    'location' => 'Workbook',
                    'message' => "Sheet '{$sheetName}' tidak dikenali. Gunakan hanya sheet bawaan template daftar mahasiswa aktif.",
                ];
            }
        }

        foreach (self::REQUIRED_SHEETS as $requiredSheet) {
            if (! isset($sheets[$requiredSheet])) {
                $errors[] = [
                    'location' => 'Workbook',
                    'message' => "Sheet wajib '{$requiredSheet}' tidak ditemukan.",
                ];
            }
        }

        if (! isset($sheets['Mahasiswa Aktif'])) {
            return $errors;
        }

        $sheet = $sheets['Mahasiswa Aktif'];
        $columnCount = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        if ($sheet->getHighestDataRow() < 5 || $columnCount < count(self::HEADERS)) {
            $errors[] = [
                'location' => 'Mahasiswa Aktif',
                'message' => 'Dimensi sheet tidak memenuhi struktur template daftar mahasiswa aktif.',
            ];

            return $errors;
        }

        foreach (self::HEADERS as $index => $header) {
            if ($this->normalize($this->value($sheet, $index + 1, 5)) !== $header) {
                $errors[] = [
                    'location' => 'Mahasiswa Aktif!'.Coordinate::stringFromColumnIndex($index + 1).'5',
                    'message' => 'Header daftar mahasiswa aktif berubah atau urutannya tidak sesuai template.',
                ];
            }
        }

        return $errors;
    }

    /**
     * @param  list<array{location: string, message: string}>  $errors
     * @return list<array{nim: string, nama_mahasiswa: string, kelas: string}>
     */
    private function parseStudents(Worksheet $sheet, array &$errors): array
    {
        $students = [];
        $nims = [];
        $lastDataRow = $sheet->getHighestDataRow();

        for ($row = 6; $row <= $lastDataRow; $row++) {
            $nim = $this->value($sheet, 1, $row);
            $name = $this->value($sheet, 2, $row);
            $class = $this->value($sheet, 3, $row);

            if ($nim === '' && $name === '' && $class === '') {
                continue;
            }

            $location = "Mahasiswa Aktif!{$row}";

            if (preg_match('/^[0-9]{9,20}$/', $nim) !== 1) {
                $errors[] = [
                    'location' => "{$location}:A",
                    'message' => 'NIM harus terdiri dari 9–20 digit angka.',
                ];
            }

            if ($name === '' || mb_strlen($name) > 100) {
                $errors[] = [
                    'location' => "{$location}:B",
                    'message' => 'Nama mahasiswa wajib diisi dan maksimal 100 karakter.',
                ];
            }

            if (preg_match('/^[1-4]AE[A-Z][1-9][0-9]*$/', $class) !== 1) {
                $errors[] = [
                    'location' => "{$location}:C",
                    'message' => 'Kode kelas wajib berformat seperti 1AEA1.',
                ];
            }

            if ($nim !== '' && isset($nims[$nim])) {
                $errors[] = [
                    'location' => "{$location}:A",
                    'message' => "NIM {$nim} duplikat dengan baris {$nims[$nim]}.",
                ];
            }
            $nims[$nim] = $row;

            $students[] = [
                'nim' => $nim,
                'nama_mahasiswa' => $name,
                'kelas' => $class,
            ];
        }

        if ($students === []) {
            $errors[] = [
                'location' => 'Mahasiswa Aktif',
                'message' => 'Daftar mahasiswa aktif belum memiliki data untuk diimpor.',
            ];
        }

        return $students;
    }

    private function value(Worksheet $sheet, int $column, int $row): string
    {
        return trim((string) $sheet->getCell([$column, $row])->getValue());
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $value) ?? ''));
    }

    /**
     * @param  list<array{location: string, message: string}>  $errors
     * @param  list<array{nim: string, nama_mahasiswa: string, kelas: string}>  $students
     * @return array{errors: list<array{location: string, message: string}>, students: list<array{nim: string, nama_mahasiswa: string, kelas: string}>, preview: array{student_count: int, class_count: int}}
     */
    private function result(array $errors, array $students): array
    {
        return [
            'errors' => $errors,
            'students' => $students,
            'preview' => [
                'student_count' => count($students),
                'class_count' => count(array_unique(array_column($students, 'kelas'))),
            ],
        ];
    }
}
