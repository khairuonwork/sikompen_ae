<?php

namespace App\Actions\KompenResponHub;

use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ParseKompenResponHubWorkbook
{
    private const NEW_CLASS_TEMPLATE_MARKER = 'TEMPLATE BLOK KELAS BARU';

    private const SUMMARY_HEADERS = [
        'NO.', 'NIM', 'NAMA MAHASISWA', 'T[J]', 'S[J]', 'I[J]', 'B[J]',
        'KOMPENSASI[J]', 'RESPONSI[J]', 'TOTAL[J]', 'KOMPENSASI DIKERJAKAN[J]', 'SISA KOMPEN[J]',
    ];

    private const DETAIL_HEADERS = [
        'NO.', 'KELAS', 'NIM', 'NAMA MAHASISWA', 'MATA KULIAH', 'NAMA DOSEN', 'TANGGAL',
        'JENIS PERTEMUAN', 'PRESENSI', 'MENIT KETERLAMBATAN', 'KETERANGAN',
        'JAM KOMPENSASI', 'JAM RESPONSI',
    ];

    /**
     * @return array{errors: list<array{location: string, message: string}>, preview: array{periode_semester: ?string, classes: list<string>, class_count: int, student_count: int, detail_count: int}, students: list<array<string, mixed>>, details: list<array<string, mixed>>}
     */
    public function execute(string $path): array
    {
        $workbook = IOFactory::load($path);
        $sheets = [];

        foreach ($workbook->getWorksheetIterator() as $sheet) {
            $sheets[$sheet->getTitle()] = $sheet;
        }

        $errors = [];
        foreach (['Kompen dan Respon', 'Detail Kompen'] as $requiredSheet) {
            if (! isset($sheets[$requiredSheet])) {
                $errors[] = [
                    'location' => 'Workbook',
                    'message' => "Sheet wajib '{$requiredSheet}' tidak ditemukan.",
                ];
            }
        }

        if ($errors !== []) {
            return $this->result($errors, [], [], [], null);
        }

        [$students, $classes, $period] = $this->parseSummary(
            $sheets['Kompen dan Respon'],
            $errors,
        );
        $details = $this->parseDetails(
            $sheets['Detail Kompen'],
            $students,
            $classes,
            $errors,
        );

        return $this->result($errors, $classes, $students, $details, $period);
    }

    /**
     * @param  list<array{location: string, message: string}>  $errors
     * @return array{0: list<array<string, mixed>>, 1: list<string>, 2: ?string}
     */
    private function parseSummary(Worksheet $sheet, array &$errors): array
    {
        $markers = $this->findClassMarkers($sheet, $errors);
        if ($markers === []) {
            return [[], [], null];
        }

        $students = [];
        $studentKeys = [];
        $periods = [];
        $classes = [];

        foreach ($markers as $marker) {
            $classMetadata = $this->classMetadataForMarker($sheet, $marker, $errors);
            if ($classMetadata === null) {
                continue;
            }

            $headerRow = $this->findSummaryHeaderRow(
                $sheet,
                $marker['column'],
                $marker['row'],
            );

            if ($headerRow === null) {
                $errors[] = [
                    'location' => $marker['location'],
                    'message' => 'Header tabel Kompen dan Respon tidak ditemukan atau urutannya berubah.',
                ];

                continue;
            }

            $classes[] = $classMetadata['class'];
            $period = $this->periodForMarker($sheet, $marker, $errors);
            if ($period !== null) {
                $periods[] = $period;
            }

            $lastDataRow = $this->lastRowForMarker($markers, $marker, $sheet, $sheet->getHighestDataRow());
            $foundStudent = false;
            $foundGap = false;

            for ($row = $headerRow + 1; $row <= $lastDataRow; $row++) {
                $nim = $this->value($sheet, $marker['column'] + 1, $row);

                if ($nim === '') {
                    $foundGap = $foundGap || $foundStudent;

                    continue;
                }

                if ($foundGap) {
                    $errors[] = [
                        'location' => "Kompen dan Respon!{$this->coordinate($marker['column'] + 1, $row)}",
                        'message' => 'NIM kosong di tengah data kelas. Rapikan baris kosong sebelum melanjutkan.',
                    ];
                }

                $foundStudent = true;
                $location = "Kompen dan Respon!{$this->coordinate($marker['column'] + 1, $row)}";
                $this->validateNim($nim, $location, $errors);

                $name = $this->value($sheet, $marker['column'] + 2, $row);
                if ($name === '') {
                    $errors[] = [
                        'location' => "Kompen dan Respon!{$this->coordinate($marker['column'] + 2, $row)}",
                        'message' => 'Nama mahasiswa wajib diisi ketika NIM terisi.',
                    ];
                }

                $studentKey = "{$classMetadata['class']}:{$nim}";
                if (isset($studentKeys[$studentKey])) {
                    $errors[] = [
                        'location' => $location,
                        'message' => "NIM {$nim} duplikat pada kelas {$classMetadata['class']}.",
                    ];
                }
                $studentKeys[$studentKey] = true;

                $compensationHours = $this->decimal($sheet, $marker['column'] + 7, $row, $errors);
                $responseHours = $this->decimal($sheet, $marker['column'] + 8, $row, $errors);

                $students[] = [
                    'nim' => $nim,
                    'nama_mahasiswa' => $name,
                    'kelas' => $classMetadata['class'],
                    'tingkat' => $classMetadata['tingkat'],
                    'total_jam_terlambat' => $this->decimal($sheet, $marker['column'] + 3, $row, $errors),
                    'total_jam_sakit' => $this->decimal($sheet, $marker['column'] + 4, $row, $errors),
                    'total_jam_izin' => $this->decimal($sheet, $marker['column'] + 5, $row, $errors),
                    'total_jam_bolos' => $this->decimal($sheet, $marker['column'] + 6, $row, $errors),
                    'total_kompensasi_jam' => $compensationHours,
                    'total_responsi_jam' => $responseHours,
                    'total_hutang_jam' => $compensationHours + $responseHours,
                    'kompensasi_dikerjakan_jam' => 0,
                    'sisa_hutang_jam' => $compensationHours + $responseHours,
                ];
            }
        }

        $periods = array_values(array_unique($periods));
        if (count($periods) > 1) {
            $errors[] = [
                'location' => 'Kompen dan Respon',
                'message' => 'Semua blok kelas harus menggunakan periode semester yang sama.',
            ];
        }

        return [
            $students,
            array_values(array_unique($classes)),
            $periods[0] ?? null,
        ];
    }

    /**
     * @param  array{class: string, column: int, row: int, location: string}  $marker
     * @param  list<array{location: string, message: string}>  $errors
     * @return array{class: string, tingkat: int}|null
     */
    private function classMetadataForMarker(Worksheet $sheet, array $marker, array &$errors): ?array
    {
        $classColumn = $marker['column'] + 4;
        $levelColumn = $marker['column'] + 7;
        $metadataRow = $marker['row'] + 1;
        $class = $this->value($sheet, $classColumn, $metadataRow);
        $level = $this->value($sheet, $levelColumn, $metadataRow);
        $hasValidClassCode = preg_match('/^[1-4]AE[A-Z][1-9][0-9]*$/', $class) === 1;
        $hasValidLevel = ctype_digit($level) && in_array((int) $level, [1, 2, 3, 4], true);
        $matchesMarker = $hasValidClassCode && $class === $marker['class'];
        $matchesClassLevel = $hasValidClassCode && $hasValidLevel && (int) $level === (int) $class[0];

        if (! $hasValidClassCode) {
            $errors[] = [
                'location' => "Kompen dan Respon!{$this->coordinate($classColumn, $metadataRow)}",
                'message' => 'Kode kelas wajib berformat seperti 1AEA1.',
            ];
        }

        if (! $hasValidLevel) {
            $errors[] = [
                'location' => "Kompen dan Respon!{$this->coordinate($levelColumn, $metadataRow)}",
                'message' => 'Tingkat wajib diisi dengan angka 1 sampai 4.',
            ];
        }

        if ($hasValidClassCode && ! $matchesMarker) {
            $errors[] = [
                'location' => "Kompen dan Respon!{$this->coordinate($classColumn, $metadataRow)}",
                'message' => 'Kode kelas harus sama dengan kode pada judul blok Kompen dan Respon.',
            ];
        }

        if ($hasValidLevel && $hasValidClassCode && ! $matchesClassLevel) {
            $errors[] = [
                'location' => "Kompen dan Respon!{$this->coordinate($levelColumn, $metadataRow)}",
                'message' => 'Tingkat harus sama dengan angka awal pada kode kelas.',
            ];
        }

        if (! $hasValidClassCode || ! $hasValidLevel || ! $matchesMarker || ! $matchesClassLevel) {
            return null;
        }

        return [
            'class' => $class,
            'tingkat' => (int) $level,
        ];
    }

    /**
     * @param  array{class: string, column: int, row: int, location: string}  $marker
     * @param  list<array{location: string, message: string}>  $errors
     */
    private function periodForMarker(Worksheet $sheet, array $marker, array &$errors): ?string
    {
        $semesterColumn = $marker['column'] + 1;
        $academicYearColumn = $marker['column'] + 2;
        $metadataRow = $marker['row'] + 1;
        $semester = $this->value($sheet, $semesterColumn, $metadataRow);
        $academicYear = $this->value($sheet, $academicYearColumn, $metadataRow);

        if ($academicYear === '' && preg_match('/^(\d{4}\/\d{4})\s+(GANJIL|GASAL|GENAP)$/iu', $semester, $matches)) {
            return "{$matches[1]} {$this->normalizedSemester($matches[2])}";
        }

        $normalizedSemester = $this->normalizedSemester($semester);
        if ($normalizedSemester === null) {
            $errors[] = [
                'location' => "Kompen dan Respon!{$this->coordinate($semesterColumn, $metadataRow)}",
                'message' => 'Semester wajib diisi dengan Gasal atau Genap.',
            ];
        }

        $hasValidAcademicYear = $this->isAcademicYear($academicYear);
        if (! $hasValidAcademicYear) {
            $errors[] = [
                'location' => "Kompen dan Respon!{$this->coordinate($academicYearColumn, $metadataRow)}",
                'message' => 'Tahun ajaran wajib berformat YYYY/YYYY dengan tahun kedua tepat satu tahun setelah tahun pertama.',
            ];
        }

        if ($normalizedSemester === null || ! $hasValidAcademicYear) {
            return null;
        }

        return "{$academicYear} {$normalizedSemester}";
    }

    /**
     * @param  list<array<string, mixed>>  $students
     * @param  list<string>  $classes
     * @param  list<array{location: string, message: string}>  $errors
     * @return list<array<string, mixed>>
     */
    private function parseDetails(
        Worksheet $sheet,
        array $students,
        array $classes,
        array &$errors,
    ): array {
        $headerRow = $this->findDetailHeaderRow($sheet);
        if ($headerRow === null) {
            $errors[] = [
                'location' => 'Detail Kompen',
                'message' => 'Header Detail Kompen tidak ditemukan atau urutannya berubah.',
            ];

            return [];
        }

        $studentKeys = [];
        foreach ($students as $student) {
            $studentKeys["{$student['kelas']}:{$student['nim']}"] = true;
        }

        $details = [];
        for ($row = $headerRow + 1; $row <= $sheet->getHighestDataRow(); $row++) {
            $values = [];
            for ($column = 1; $column <= 13; $column++) {
                $values[] = $this->value($sheet, $column, $row);
            }

            if (count(array_filter($values)) === 0) {
                continue;
            }

            [, $class, $nim, $name, $course, $lecturer, , $meeting, $attendance] = $values;
            $location = "Detail Kompen!{$row}";

            if (! in_array($class, $classes, true)) {
                $errors[] = ['location' => "{$location}:B", 'message' => 'Kelas tidak ditemukan di Kompen dan Respon.'];
            }

            $this->validateNim($nim, "{$location}:C", $errors);
            if (! isset($studentKeys["{$class}:{$nim}"])) {
                $errors[] = ['location' => "{$location}:C", 'message' => 'NIM tidak ditemukan pada kelas yang sama di Kompen dan Respon.'];
            }

            foreach (['Nama Mahasiswa' => $name, 'Mata Kuliah' => $course, 'Nama Dosen' => $lecturer] as $label => $value) {
                if ($value === '') {
                    $errors[] = ['location' => $location, 'message' => "{$label} wajib diisi pada detail yang terisi."];
                }
            }

            if (! in_array($meeting, ['Luring', 'Daring', 'Praktek'], true)) {
                $errors[] = ['location' => "{$location}:H", 'message' => 'Jenis pertemuan harus Luring, Daring, atau Praktek.'];
            }

            if (! in_array($attendance, ['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Tidak Hadir'], true)) {
                $errors[] = ['location' => "{$location}:I", 'message' => 'Nilai presensi tidak valid.'];
            }

            $details[] = [
                'kelas' => $class,
                'nim' => $nim,
                'tanggal' => $this->date($sheet, 7, $row, $errors),
                'mata_kuliah' => $course,
                'nama_dosen' => $lecturer,
                'jenis_pertemuan' => $meeting,
                'presensi' => $attendance,
                'menit_keterlambatan' => $this->integer($sheet, 10, $row, $errors),
                'keterangan' => $values[10] ?: null,
                'jam_kompensasi' => $this->decimal($sheet, 12, $row, $errors, 'Detail Kompen'),
                'jam_responsi' => $this->decimal($sheet, 13, $row, $errors, 'Detail Kompen'),
            ];
        }

        return $details;
    }

    /** @return list<array{class: string, column: int, row: int, location: string}> */
    private function findClassMarkers(Worksheet $sheet, array &$errors): array
    {
        $markers = [];
        $seenClasses = [];
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
            for ($column = 1; $column <= $highestColumn; $column++) {
                $value = $this->value($sheet, $column, $row);
                if (! preg_match('/^KOMPEN DAN RESPON\\s*[—-]\\s*([1-4]AE[A-Z][1-9][0-9]*)$/u', $value, $matches)) {
                    continue;
                }

                $class = $matches[1];
                if (isset($seenClasses[$class])) {
                    continue;
                }

                $seenClasses[$class] = true;
                $markers[] = [
                    'class' => $class,
                    'column' => $column,
                    'row' => $row,
                    'location' => "Kompen dan Respon!{$this->coordinate($column, $row)}",
                ];
            }
        }

        if ($markers === []) {
            $errors[] = [
                'location' => 'Kompen dan Respon',
                'message' => 'Tidak ada marker kelas yang valid.',
            ];
        }

        return $markers;
    }

    /**
     * @param  list<array{class: string, column: int, row: int, location: string}>  $markers
     * @param  array{class: string, column: int, row: int, location: string}  $marker
     */
    private function lastRowForMarker(array $markers, array $marker, Worksheet $sheet, int $highestRow): int
    {
        $nextRow = $highestRow + 1;
        foreach ($markers as $otherMarker) {
            if ($otherMarker['column'] === $marker['column'] && $otherMarker['row'] > $marker['row']) {
                $nextRow = min($nextRow, $otherMarker['row']);
            }
        }

        for ($row = $marker['row'] + 1; $row <= $highestRow; $row++) {
            $value = $this->normalize($this->value($sheet, $marker['column'], $row));

            if (str_starts_with($value, self::NEW_CLASS_TEMPLATE_MARKER)) {
                $nextRow = min($nextRow, $row);
            }
        }

        return $nextRow - 1;
    }

    private function findSummaryHeaderRow(Worksheet $sheet, int $column, int $markerRow): ?int
    {
        for ($row = $markerRow + 1; $row <= $markerRow + 4; $row++) {
            if ($this->hasHeaders($sheet, $column, $row, self::SUMMARY_HEADERS)) {
                return $row;
            }
        }

        return null;
    }

    private function findDetailHeaderRow(Worksheet $sheet): ?int
    {
        for ($row = 1; $row <= min(50, $sheet->getHighestDataRow()); $row++) {
            if ($this->hasHeaders($sheet, 1, $row, self::DETAIL_HEADERS)) {
                return $row;
            }
        }

        return null;
    }

    /** @param list<string> $expected */
    private function hasHeaders(Worksheet $sheet, int $column, int $row, array $expected): bool
    {
        foreach ($expected as $offset => $header) {
            if ($this->normalize($this->value($sheet, $column + $offset, $row)) !== $header) {
                return false;
            }
        }

        return true;
    }

    /** @param list<array{location: string, message: string}> $errors */
    private function decimal(Worksheet $sheet, int $column, int $row, array &$errors, string $sheetName = 'Kompen dan Respon'): float
    {
        $value = $this->value($sheet, $column, $row);
        if ($value === '') {
            return 0;
        }

        $normalizedValue = str_replace(',', '.', $value);
        if (! is_numeric($normalizedValue)) {
            $errors[] = ['location' => "{$sheetName}!{$this->coordinate($column, $row)}", 'message' => 'Nilai jam harus berupa angka.'];

            return 0;
        }

        return (float) $normalizedValue;
    }

    /** @param list<array{location: string, message: string}> $errors */
    private function integer(Worksheet $sheet, int $column, int $row, array &$errors): int
    {
        $value = $this->value($sheet, $column, $row);
        if ($value === '') {
            return 0;
        }

        if (! ctype_digit($value)) {
            $errors[] = ['location' => "Detail Kompen!{$this->coordinate($column, $row)}", 'message' => 'Menit keterlambatan harus bilangan bulat tidak negatif.'];

            return 0;
        }

        return (int) $value;
    }

    /** @param list<array{location: string, message: string}> $errors */
    private function date(Worksheet $sheet, int $column, int $row, array &$errors): ?string
    {
        $raw = $sheet->getCell([$column, $row])->getValue();
        if ($raw === null || $raw === '') {
            $errors[] = ['location' => "Detail Kompen!{$this->coordinate($column, $row)}", 'message' => 'Tanggal wajib diisi.'];

            return null;
        }

        try {
            return is_numeric($raw)
                ? ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d')
                : CarbonImmutable::parse((string) $raw)->format('Y-m-d');
        } catch (\Throwable) {
            $errors[] = ['location' => "Detail Kompen!{$this->coordinate($column, $row)}", 'message' => 'Tanggal tidak valid.'];

            return null;
        }
    }

    /** @param list<array{location: string, message: string}> $errors */
    private function validateNim(string $nim, string $location, array &$errors): void
    {
        if (! preg_match('/^[0-9]{9,20}$/', $nim)) {
            $errors[] = ['location' => $location, 'message' => 'NIM harus terdiri dari 9–20 digit angka.'];
        }
    }

    private function normalizedSemester(string $semester): ?string
    {
        return match (mb_strtolower(trim($semester))) {
            'ganjil', 'gasal' => 'Gasal',
            'genap' => 'Genap',
            default => null,
        };
    }

    private function isAcademicYear(string $academicYear): bool
    {
        if (preg_match('/^(\d{4})\/(\d{4})$/', $academicYear, $matches) !== 1) {
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
     * @param  list<string>  $classes
     * @param  list<array<string, mixed>>  $students
     * @param  list<array<string, mixed>>  $details
     * @return array{errors: list<array{location: string, message: string}>, preview: array{periode_semester: ?string, classes: list<string>, class_count: int, student_count: int, detail_count: int}, students: list<array<string, mixed>>, details: list<array<string, mixed>>}
     */
    private function result(array $errors, array $classes, array $students, array $details, ?string $period): array
    {
        return [
            'errors' => $errors,
            'preview' => [
                'periode_semester' => $period,
                'classes' => $classes,
                'class_count' => count($classes),
                'student_count' => count($students),
                'detail_count' => count($details),
            ],
            'students' => $students,
            'details' => $details,
        ];
    }
}
