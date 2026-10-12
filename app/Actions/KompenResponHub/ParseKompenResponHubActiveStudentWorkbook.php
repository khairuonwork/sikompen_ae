<?php

namespace App\Actions\KompenResponHub;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ParseKompenResponHubActiveStudentWorkbook
{
    private const NEW_CLASS_TEMPLATE_MARKER = 'TEMPLATE BLOK KELAS BARU';

    private const REQUIRED_SHEETS = ['Petunjuk', 'Mahasiswa Aktif'];

    private const HEADERS = ['NO.', 'NIM', 'NAMA MAHASISWA'];

    /**
     * @return array{errors: list<array{location: string, message: string}>, students: list<array{nim: string, nama_mahasiswa: string, kelas: string}>, preview: array{periode_semester: ?string, student_count: int, class_count: int}}
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
            return $this->result($errors, [], null);
        }

        $sheet = $sheets['Mahasiswa Aktif'];
        $this->validateNoFormulas($sheet, $errors);
        $markers = $this->findClassMarkers($sheet, $errors);
        $students = [];
        $periods = [];
        $nims = [];

        foreach ($markers as $marker) {
            $metadata = $this->classMetadataForMarker($sheet, $marker, $errors);
            $period = $this->periodForMarker($sheet, $marker, $errors);
            $headerRow = $this->findHeaderRow($sheet, $marker['column'], $marker['row']);

            if ($headerRow === null) {
                $errors[] = [
                    'location' => $marker['location'],
                    'message' => 'Header daftar mahasiswa aktif tidak ditemukan atau urutannya berubah.',
                ];

                continue;
            }

            if ($metadata === null || $period === null) {
                continue;
            }

            $periods[] = $period;
            $this->parseStudents(
                $sheet,
                $marker,
                $metadata['class'],
                $headerRow,
                $this->lastRowForMarker($markers, $marker, $sheet),
                $students,
                $nims,
                $errors,
            );
        }

        $uniquePeriods = array_values(array_unique($periods));
        if (count($uniquePeriods) > 1) {
            $errors[] = [
                'location' => 'Mahasiswa Aktif',
                'message' => 'Semua blok kelas harus menggunakan periode semester yang sama.',
            ];
        }

        if ($students === []) {
            $errors[] = [
                'location' => 'Mahasiswa Aktif',
                'message' => 'Daftar mahasiswa aktif belum memiliki data untuk diimpor.',
            ];
        }

        return $this->result($errors, $students, $uniquePeriods[0] ?? null);
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

        if ($sheet->getHighestDataRow() < 5 || $columnCount < 8) {
            $errors[] = [
                'location' => 'Mahasiswa Aktif',
                'message' => 'Dimensi sheet tidak memenuhi struktur blok daftar mahasiswa aktif.',
            ];
        }

        return $errors;
    }

    /** @param list<array{location: string, message: string}> $errors */
    private function validateNoFormulas(Worksheet $sheet, array &$errors): void
    {
        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($row = 1; $row <= $lastRow; $row++) {
            for ($column = 1; $column <= $lastColumn; $column++) {
                $value = $sheet->getCell([$column, $row])->getValue();

                if (! is_string($value) || ! str_starts_with(trim($value), '=')) {
                    continue;
                }

                $errors[] = [
                    'location' => "Mahasiswa Aktif!{$this->coordinate($column, $row)}",
                    'message' => 'Formula tidak diizinkan dalam template daftar mahasiswa aktif.',
                ];
            }
        }
    }

    /**
     * @param  list<array{location: string, message: string}>  $errors
     * @return list<array{class: string, column: int, row: int, location: string}>
     */
    private function findClassMarkers(Worksheet $sheet, array &$errors): array
    {
        $markers = [];
        $seenClasses = [];
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
            for ($column = 1; $column <= $highestColumn; $column++) {
                $value = $this->value($sheet, $column, $row);
                if (preg_match('/^DAFTAR MAHASISWA AKTIF\\s*[—-]\\s*([1-4]AE[A-Z][1-9][0-9]*)$/u', $value, $matches) !== 1) {
                    continue;
                }

                $class = $matches[1];
                if (isset($seenClasses[$class])) {
                    $errors[] = [
                        'location' => "Mahasiswa Aktif!{$this->coordinate($column, $row)}",
                        'message' => "Blok kelas {$class} muncul lebih dari satu kali.",
                    ];

                    continue;
                }

                $seenClasses[$class] = true;
                $markers[] = [
                    'class' => $class,
                    'column' => $column,
                    'row' => $row,
                    'location' => "Mahasiswa Aktif!{$this->coordinate($column, $row)}",
                ];
            }
        }

        if ($markers === []) {
            $errors[] = [
                'location' => 'Mahasiswa Aktif',
                'message' => 'Tidak ada blok kelas daftar mahasiswa aktif yang valid.',
            ];
        }

        return $markers;
    }

    /**
     * @param  array{class: string, column: int, row: int, location: string}  $marker
     * @param  list<array{location: string, message: string}>  $errors
     * @return array{class: string, tingkat: int}|null
     */
    private function classMetadataForMarker(Worksheet $sheet, array $marker, array &$errors): ?array
    {
        $metadataRow = $marker['row'] + 1;
        $classColumn = $marker['column'] + 4;
        $levelColumn = $marker['column'] + 7;
        $class = $this->value($sheet, $classColumn, $metadataRow);
        $level = $this->value($sheet, $levelColumn, $metadataRow);
        $hasValidClass = preg_match('/^[1-4]AE[A-Z][1-9][0-9]*$/', $class) === 1;
        $hasValidLevel = ctype_digit($level) && in_array((int) $level, [1, 2, 3, 4], true);

        if (! $hasValidClass) {
            $errors[] = [
                'location' => "Mahasiswa Aktif!{$this->coordinate($classColumn, $metadataRow)}",
                'message' => 'Kode kelas wajib berformat seperti 1AEA1.',
            ];
        }

        if (! $hasValidLevel) {
            $errors[] = [
                'location' => "Mahasiswa Aktif!{$this->coordinate($levelColumn, $metadataRow)}",
                'message' => 'Tingkat wajib diisi dengan angka 1 sampai 4.',
            ];
        }

        if ($hasValidClass && $class !== $marker['class']) {
            $errors[] = [
                'location' => "Mahasiswa Aktif!{$this->coordinate($classColumn, $metadataRow)}",
                'message' => 'Kode kelas harus sama dengan kode pada judul blok daftar mahasiswa aktif.',
            ];
        }

        if ($hasValidClass && $hasValidLevel && (int) $class[0] !== (int) $level) {
            $errors[] = [
                'location' => "Mahasiswa Aktif!{$this->coordinate($levelColumn, $metadataRow)}",
                'message' => 'Tingkat harus sama dengan angka awal pada kode kelas.',
            ];
        }

        if (! $hasValidClass || ! $hasValidLevel || $class !== $marker['class'] || (int) $class[0] !== (int) $level) {
            return null;
        }

        return ['class' => $class, 'tingkat' => (int) $level];
    }

    /**
     * @param  array{class: string, column: int, row: int, location: string}  $marker
     * @param  list<array{location: string, message: string}>  $errors
     */
    private function periodForMarker(Worksheet $sheet, array $marker, array &$errors): ?string
    {
        $metadataRow = $marker['row'] + 1;
        $semesterColumn = $marker['column'] + 1;
        $academicYearColumn = $marker['column'] + 2;
        $semester = $this->normalizedSemester($this->value($sheet, $semesterColumn, $metadataRow));
        $academicYear = $this->value($sheet, $academicYearColumn, $metadataRow);

        if ($semester === null) {
            $errors[] = [
                'location' => "Mahasiswa Aktif!{$this->coordinate($semesterColumn, $metadataRow)}",
                'message' => 'Semester wajib diisi dengan Gasal atau Genap.',
            ];
        }

        if (! $this->isAcademicYear($academicYear)) {
            $errors[] = [
                'location' => "Mahasiswa Aktif!{$this->coordinate($academicYearColumn, $metadataRow)}",
                'message' => 'Tahun ajaran wajib berformat YYYY/YYYY dengan tahun kedua tepat satu tahun setelah tahun pertama.',
            ];
        }

        if ($semester === null || ! $this->isAcademicYear($academicYear)) {
            return null;
        }

        return "{$academicYear} {$semester}";
    }

    /**
     * @param  list<array{class: string, column: int, row: int, location: string}>  $markers
     * @param  array{class: string, column: int, row: int, location: string}  $marker
     */
    private function lastRowForMarker(array $markers, array $marker, Worksheet $sheet): int
    {
        $nextRow = $sheet->getHighestDataRow() + 1;

        foreach ($markers as $otherMarker) {
            if ($otherMarker['column'] === $marker['column'] && $otherMarker['row'] > $marker['row']) {
                $nextRow = min($nextRow, $otherMarker['row']);
            }
        }

        for ($row = $marker['row'] + 1; $row <= $sheet->getHighestDataRow(); $row++) {
            $value = $this->normalize($this->value($sheet, $marker['column'], $row));

            if (str_starts_with($value, self::NEW_CLASS_TEMPLATE_MARKER)) {
                $nextRow = min($nextRow, $row);
            }
        }

        return $nextRow - 1;
    }

    private function findHeaderRow(Worksheet $sheet, int $column, int $markerRow): ?int
    {
        for ($row = $markerRow + 1; $row <= $markerRow + 4; $row++) {
            if ($this->hasHeaders($sheet, $column, $row)) {
                return $row;
            }
        }

        return null;
    }

    private function hasHeaders(Worksheet $sheet, int $column, int $row): bool
    {
        foreach (self::HEADERS as $offset => $header) {
            if ($this->normalize($this->value($sheet, $column + $offset, $row)) !== $header) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{class: string, column: int, row: int, location: string}  $marker
     * @param  list<array{nim: string, nama_mahasiswa: string, kelas: string}>  $students
     * @param  array<string, string>  $nims
     * @param  list<array{location: string, message: string}>  $errors
     */
    private function parseStudents(Worksheet $sheet, array $marker, string $class, int $headerRow, int $lastDataRow, array &$students, array &$nims, array &$errors): void
    {
        $foundStudent = false;
        $foundGap = false;

        for ($row = $headerRow + 1; $row <= $lastDataRow; $row++) {
            $nim = $this->value($sheet, $marker['column'] + 1, $row);
            $name = $this->value($sheet, $marker['column'] + 2, $row);

            if ($nim === '' && $name === '') {
                $foundGap = $foundGap || $foundStudent;

                continue;
            }

            $nimLocation = "Mahasiswa Aktif!{$this->coordinate($marker['column'] + 1, $row)}";
            $nameLocation = "Mahasiswa Aktif!{$this->coordinate($marker['column'] + 2, $row)}";
            if ($foundGap) {
                $errors[] = [
                    'location' => $nimLocation,
                    'message' => 'Data mahasiswa terisi setelah baris kosong. Rapikan baris kosong sebelum melanjutkan.',
                ];
            }

            $foundStudent = true;
            if (preg_match('/^[0-9]{9,20}$/', $nim) !== 1) {
                $errors[] = ['location' => $nimLocation, 'message' => 'NIM harus terdiri dari 9–20 digit angka.'];
            }

            if ($name === '' || mb_strlen($name) > 100) {
                $errors[] = ['location' => $nameLocation, 'message' => 'Nama mahasiswa wajib diisi dan maksimal 100 karakter.'];
            }

            if ($nim !== '' && isset($nims[$nim])) {
                $errors[] = ['location' => $nimLocation, 'message' => "NIM {$nim} duplikat dengan {$nims[$nim]}."];
            }

            if ($nim !== '') {
                $nims[$nim] = $nimLocation;
            }

            $students[] = ['nim' => $nim, 'nama_mahasiswa' => $name, 'kelas' => $class];
        }
    }

    private function normalizedSemester(string $semester): ?string
    {
        return match (mb_strtolower(trim($semester))) {
            'gasal' => 'Gasal',
            'genap' => 'Genap',
            default => null,
        };
    }

    private function isAcademicYear(string $academicYear): bool
    {
        if (preg_match('/^(\\d{4})\\/(\\d{4})$/', $academicYear, $matches) !== 1) {
            return false;
        }

        return (int) $matches[2] === (int) $matches[1] + 1;
    }

    private function value(Worksheet $sheet, int $column, int $row): string
    {
        return trim((string) $sheet->getCell([$column, $row])->getValue());
    }

    private function coordinate(int $column, int $row): string
    {
        return Coordinate::stringFromColumnIndex($column).$row;
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(trim(preg_replace('/\\s+/u', ' ', $value) ?? ''));
    }

    /**
     * @param  list<array{location: string, message: string}>  $errors
     * @param  list<array{nim: string, nama_mahasiswa: string, kelas: string}>  $students
     * @return array{errors: list<array{location: string, message: string}>, students: list<array{nim: string, nama_mahasiswa: string, kelas: string}>, preview: array{periode_semester: ?string, student_count: int, class_count: int}}
     */
    private function result(array $errors, array $students, ?string $period): array
    {
        return [
            'errors' => $errors,
            'students' => $students,
            'preview' => [
                'periode_semester' => $period,
                'student_count' => count($students),
                'class_count' => count(array_unique(array_column($students, 'kelas'))),
            ],
        ];
    }
}
