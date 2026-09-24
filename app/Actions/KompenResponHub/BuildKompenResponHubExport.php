<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubExportTask;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BuildKompenResponHubExport
{
    private const BrandNavy = '1F4E78';

    private const ContextBlue = 'DCE6F1';

    private const GridGrey = 'E5E7EB';

    public function __construct(private KompenResponHubDataQuery $dataQuery) {}

    /** @return array{output_path: string, download_filename: string} */
    public function execute(KompenResponHubExportTask $task): array
    {
        $filters = $task->filters;
        $definition = $this->definition($task->resource);
        $records = $this->records($task->resource, $filters);
        $period = $filters['periode_semester'] ?? 'Semua Periode';
        $periodForFilename = Str::slug(str_replace('/', '-', $period));
        $filename = "{$definition['slug']}-{$periodForFilename}-{$task->id}.{$task->format}";
        $outputPath = "kompen-respon-hub/exports/{$task->access_token}.{$task->format}";

        Storage::disk('local')->makeDirectory('kompen-respon-hub/exports');

        if ($task->format === 'xlsx') {
            $this->writeSpreadsheet(
                Storage::disk('local')->path($outputPath),
                $definition['title'],
                $period,
                $records,
                $definition['columns'],
            );
        } else {
            $this->writePdf(
                Storage::disk('local')->path($outputPath),
                $definition['title'],
                $period,
                $records,
                $definition['columns'],
            );
        }

        return ['output_path' => $outputPath, 'download_filename' => $filename];
    }

    /** @param array<string, mixed> $filters */
    private function records(string $resource, array $filters): Collection
    {
        return match ($resource) {
            'students' => $this->dataQuery->students($filters)->get(),
            'details' => $this->dataQuery->details($filters)->get(),
            'warnings' => $this->dataQuery->warnings($filters)->get(),
        };
    }

    /**
     * @return array{slug: string, title: string, columns: list<array{field: string, heading: string, type: 'text'|'integer'|'hours'|'date', width: int}>}
     */
    private function definition(string $resource): array
    {
        return match ($resource) {
            'students' => [
                'slug' => 'kompen-dan-respon',
                'title' => 'Kompen dan Respon',
                'columns' => $this->studentColumns(),
            ],
            'details' => [
                'slug' => 'detail-kompen',
                'title' => 'Detail Kompen',
                'columns' => $this->detailColumns(),
            ],
            'warnings' => [
                'slug' => 'surat-peringatan',
                'title' => 'Surat Peringatan',
                'columns' => $this->warningColumns(),
            ],
        };
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $records
     * @param  list<array{field: string, heading: string, type: 'text'|'integer'|'hours'|'date', width: int}>  $columns
     */
    private function writeSpreadsheet(string $path, string $title, string $period, Collection $records, array $columns): void
    {
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle(Str::limit($title, 31, ''));
        $this->writeSpreadsheetHeader($worksheet, $title, $period, $columns);

        foreach ($records as $rowIndex => $record) {
            $excelRow = $rowIndex + 5;

            foreach ($columns as $columnIndex => $column) {
                $cell = Coordinate::stringFromColumnIndex($columnIndex + 1).$excelRow;
                $value = data_get($record, $column['field']);

                if (in_array($column['type'], ['integer', 'hours'], true)) {
                    $worksheet->setCellValue($cell, (float) $value);
                    $worksheet->getStyle($cell)->getNumberFormat()->setFormatCode($column['type'] === 'hours' ? '#,##0.00' : '#,##0');
                    $worksheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    continue;
                }

                $worksheet->setCellValueExplicit($cell, $this->spreadsheetValue($value), DataType::TYPE_STRING);
                $worksheet->getStyle($cell)->getAlignment()->setHorizontal($column['type'] === 'date' ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT);
            }

            $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
            $worksheet->getStyle("A{$excelRow}:{$lastColumn}{$excelRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->setColor(new Color(self::GridGrey));
            $worksheet->getStyle("A{$excelRow}:{$lastColumn}{$excelRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        $this->configureSpreadsheetPrintLayout($worksheet, count($columns));
        (new Xlsx($spreadsheet))->save($path);
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $records
     * @param  list<array{field: string, heading: string, type: 'text'|'integer'|'hours'|'date', width: int}>  $columns
     */
    private function writePdf(string $path, string $title, string $period, Collection $records, array $columns): void
    {
        $dompdf = new Dompdf(new Options(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]));
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml(view('exports.kompen-respon-hub', compact('columns', 'period', 'records', 'title'))->render(), 'UTF-8');
        $dompdf->render();

        file_put_contents($path, $dompdf->output());
    }

    /** @param list<array{field: string, heading: string, type: 'text'|'integer'|'hours'|'date', width: int}> $columns */
    private function writeSpreadsheetHeader(Worksheet $worksheet, string $title, string $period, array $columns): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
        $worksheet->mergeCells("A1:{$lastColumn}1");
        $worksheet->setCellValue('A1', $title);
        $worksheet->setCellValue('A2', 'Periode');
        $worksheet->setCellValue('B2', $period);
        $worksheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => self::BrandNavy]],
            'font' => ['bold' => true, 'color' => ['rgb' => Color::COLOR_WHITE], 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $worksheet->getStyle('A2:B2')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => self::ContextBlue]],
            'font' => ['bold' => true, 'color' => ['rgb' => '1F2937']],
        ]);

        foreach ($columns as $columnIndex => $column) {
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex + 1);
            $worksheet->setCellValue("{$columnLetter}4", $column['heading']);
            $worksheet->getColumnDimension($columnLetter)->setWidth($column['width']);
        }

        $worksheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => self::BrandNavy]],
            'font' => ['bold' => true, 'color' => ['rgb' => Color::COLOR_WHITE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $worksheet->getRowDimension(1)->setRowHeight(24);
        $worksheet->getRowDimension(4)->setRowHeight(28);
    }

    private function configureSpreadsheetPrintLayout(Worksheet $worksheet, int $columnCount): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $worksheet->setShowGridlines(false);
        $worksheet->freezePane('A5');
        $worksheet->setAutoFilter("A4:{$lastColumn}4");
        $worksheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        $worksheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setBottom(0.35)->setLeft(0.25);
    }

    private function spreadsheetValue(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }

    /** @return list<array{field: string, heading: string, type: 'text'|'integer'|'hours'|'date', width: int}> */
    private function studentColumns(): array
    {
        return [
            ['field' => 'tingkat', 'heading' => 'Tingkat', 'type' => 'integer', 'width' => 10],
            ['field' => 'nim', 'heading' => 'NIM', 'type' => 'text', 'width' => 16],
            ['field' => 'nama_mahasiswa', 'heading' => 'Nama Mahasiswa', 'type' => 'text', 'width' => 26],
            ['field' => 'kelas', 'heading' => 'Kelas', 'type' => 'text', 'width' => 12],
            ['field' => 'periode_semester', 'heading' => 'Periode', 'type' => 'text', 'width' => 20],
            ['field' => 'total_jam_terlambat', 'heading' => 'T[j]', 'type' => 'hours', 'width' => 11],
            ['field' => 'total_jam_sakit', 'heading' => 'S[j]', 'type' => 'hours', 'width' => 11],
            ['field' => 'total_jam_izin', 'heading' => 'I[j]', 'type' => 'hours', 'width' => 11],
            ['field' => 'total_jam_bolos', 'heading' => 'B[j]', 'type' => 'hours', 'width' => 11],
            ['field' => 'effective_total_kompensasi_jam', 'heading' => 'Kompensasi[j]', 'type' => 'hours', 'width' => 15],
            ['field' => 'effective_total_responsi_jam', 'heading' => 'Responsi[j]', 'type' => 'hours', 'width' => 14],
            ['field' => 'effective_total_hutang_jam', 'heading' => 'Total[j]', 'type' => 'hours', 'width' => 12],
            ['field' => 'effective_kompensasi_dikerjakan_jam', 'heading' => 'Komp. dikerjakan[j]', 'type' => 'hours', 'width' => 18],
            ['field' => 'effective_responsi_dikerjakan_jam', 'heading' => 'Resp. dikerjakan[j]', 'type' => 'hours', 'width' => 18],
            ['field' => 'effective_sisa_hutang_jam', 'heading' => 'Sisa[j]', 'type' => 'hours', 'width' => 13],
        ];
    }

    /** @return list<array{field: string, heading: string, type: 'text'|'integer'|'hours'|'date', width: int}> */
    private function detailColumns(): array
    {
        return [
            ['field' => 'student.tingkat', 'heading' => 'Tingkat', 'type' => 'integer', 'width' => 10],
            ['field' => 'student.nim', 'heading' => 'NIM', 'type' => 'text', 'width' => 16],
            ['field' => 'student.nama_mahasiswa', 'heading' => 'Nama Mahasiswa', 'type' => 'text', 'width' => 26],
            ['field' => 'student.kelas', 'heading' => 'Kelas', 'type' => 'text', 'width' => 12],
            ['field' => 'student.periode_semester', 'heading' => 'Periode', 'type' => 'text', 'width' => 20],
            ['field' => 'effective_mata_kuliah', 'heading' => 'Mata Kuliah', 'type' => 'text', 'width' => 24],
            ['field' => 'effective_nama_dosen', 'heading' => 'Nama Dosen', 'type' => 'text', 'width' => 22],
            ['field' => 'effective_tanggal', 'heading' => 'Tanggal', 'type' => 'date', 'width' => 14],
            ['field' => 'effective_jenis_pertemuan', 'heading' => 'Jenis Pertemuan', 'type' => 'text', 'width' => 18],
            ['field' => 'effective_presensi', 'heading' => 'Presensi', 'type' => 'text', 'width' => 14],
            ['field' => 'effective_menit_keterlambatan', 'heading' => 'Menit Terlambat', 'type' => 'integer', 'width' => 16],
            ['field' => 'effective_keterangan', 'heading' => 'Keterangan', 'type' => 'text', 'width' => 28],
            ['field' => 'effective_jam_kompensasi', 'heading' => 'Jam Kompensasi', 'type' => 'hours', 'width' => 17],
            ['field' => 'effective_jam_responsi', 'heading' => 'Jam Responsi', 'type' => 'hours', 'width' => 15],
        ];
    }

    /** @return list<array{field: string, heading: string, type: 'text'|'integer'|'hours'|'date', width: int}> */
    private function warningColumns(): array
    {
        return [
            ['field' => 'nim', 'heading' => 'NIM', 'type' => 'text', 'width' => 16],
            ['field' => 'nama_mahasiswa', 'heading' => 'Nama Mahasiswa', 'type' => 'text', 'width' => 26],
            ['field' => 'kelas', 'heading' => 'Kelas', 'type' => 'text', 'width' => 12],
            ['field' => 'classification', 'heading' => 'Indikator', 'type' => 'text', 'width' => 14],
            ['field' => 'letter_status', 'heading' => 'Status SP-1', 'type' => 'text', 'width' => 16],
            ['field' => 'resolution', 'heading' => 'Penyelesaian', 'type' => 'text', 'width' => 16],
            ['field' => 'snapshot.sisa_hutang_jam', 'heading' => 'Sisa[j]', 'type' => 'hours', 'width' => 13],
            ['field' => 'issued_at', 'heading' => 'Diterbitkan', 'type' => 'date', 'width' => 16],
            ['field' => 'reason', 'heading' => 'Catatan', 'type' => 'text', 'width' => 30],
        ];
    }
}
