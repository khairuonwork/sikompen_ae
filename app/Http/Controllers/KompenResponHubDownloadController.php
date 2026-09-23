<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Http\Requests\DownloadKompenResponHubDataRequest;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KompenResponHubDownloadController extends Controller
{
    private const ExportLimit = 5000;

    private const PdfExportLimit = 1000;

    private const BrandNavy = '1F4E78';

    private const ContextBlue = 'DCE6F1';

    private const GridGrey = 'E5E7EB';

    public function __construct(private KompenResponHubDataQuery $dataQuery) {}

    public function students(DownloadKompenResponHubDataRequest $request): StreamedResponse
    {
        $filters = $request->validated();

        return $this->downloadSpreadsheet(
            'kompen-dan-respon',
            $filters['periode_semester'],
            $this->dataQuery->students($filters)->limit(self::ExportLimit + 1)->get(),
            'Kompen dan Respon',
            $this->studentColumns(),
        );
    }

    public function details(DownloadKompenResponHubDataRequest $request): StreamedResponse
    {
        $filters = $request->validated();

        return $this->downloadSpreadsheet(
            'detail-kompen',
            $filters['periode_semester'],
            $this->dataQuery->details($filters)->limit(self::ExportLimit + 1)->get(),
            'Detail Kompen',
            $this->detailColumns(),
        );
    }

    public function studentsPdf(DownloadKompenResponHubDataRequest $request): Response
    {
        $filters = $request->validated();

        return $this->downloadPdf(
            'kompen-dan-respon',
            $filters['periode_semester'],
            $this->dataQuery->students($filters)->limit(self::PdfExportLimit + 1)->get(),
            'Kompen dan Respon',
            $this->studentColumns(),
        );
    }

    public function detailsPdf(DownloadKompenResponHubDataRequest $request): Response
    {
        $filters = $request->validated();

        return $this->downloadPdf(
            'detail-kompen',
            $filters['periode_semester'],
            $this->dataQuery->details($filters)->limit(self::PdfExportLimit + 1)->get(),
            'Detail Kompen',
            $this->detailColumns(),
        );
    }

    public function warnings(DownloadKompenResponHubDataRequest $request): StreamedResponse
    {
        $filters = $request->validated();

        return $this->downloadSpreadsheet(
            'surat-peringatan',
            $filters['periode_semester'],
            $this->dataQuery->warnings($filters)->limit(self::ExportLimit + 1)->get(),
            'Surat Peringatan',
            $this->warningColumns(),
        );
    }

    public function warningsPdf(DownloadKompenResponHubDataRequest $request): Response
    {
        $filters = $request->validated();

        return $this->downloadPdf(
            'surat-peringatan',
            $filters['periode_semester'],
            $this->dataQuery->warnings($filters)->limit(self::PdfExportLimit + 1)->get(),
            'Surat Peringatan',
            $this->warningColumns(),
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $records
     * @param  list<array{field: string, heading: string, type: 'text'|'integer'|'hours'|'date', width: int}>  $columns
     */
    private function downloadSpreadsheet(
        string $table,
        string $period,
        Collection $records,
        string $title,
        array $columns,
    ): StreamedResponse {
        $this->ensureWithinLimit($records, self::ExportLimit, 'XLSX');

        $filename = $this->filename($table, $period, 'xlsx');

        return response()->streamDownload(function () use ($columns, $period, $records, $title): void {
            $spreadsheet = new Spreadsheet;
            $worksheet = $spreadsheet->getActiveSheet();
            $worksheet->setTitle(Str::limit($title, 31, ''));

            $this->writeSpreadsheetHeader($worksheet, $title, $period, $columns);
            $this->writeSpreadsheetRows($worksheet, $records, $columns);
            $this->configureSpreadsheetPrintLayout($worksheet, count($columns));

            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $records
     * @param  list<array{field: string, heading: string, type: 'text'|'integer'|'hours'|'date', width: int}>  $columns
     */
    private function downloadPdf(
        string $table,
        string $period,
        Collection $records,
        string $title,
        array $columns,
    ): Response {
        $this->ensureWithinLimit($records, self::PdfExportLimit, 'PDF');

        $dompdf = new Dompdf(new Options([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
        ]));
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml(
            view('exports.kompen-respon-hub', [
                'columns' => $columns,
                'period' => $period,
                'records' => $records,
                'title' => $title,
            ])->render(),
            'UTF-8',
        );
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Disposition' => 'attachment; filename="'.$this->filename($table, $period, 'pdf').'"',
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $records
     */
    private function ensureWithinLimit(Collection $records, int $limit, string $format): void
    {
        abort_if(
            $records->count() > $limit,
            422,
            "Hasil ekspor {$format} melebihi {$limit} baris. Tambahkan filter kelas, tingkat, atau nama.",
        );
    }

    /**
     * @param  list<array{field: string, heading: string, type: 'text'|'integer'|'hours'|'date', width: int}>  $columns
     */
    private function writeSpreadsheetHeader(
        Worksheet $worksheet,
        string $title,
        string $period,
        array $columns,
    ): void {
        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));

        $worksheet->mergeCells("A1:{$lastColumn}1");
        $worksheet->setCellValue('A1', $title);
        $worksheet->setCellValue('A2', 'Periode');
        $worksheet->setCellValue('B2', $period);

        $worksheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => self::BrandNavy],
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => Color::COLOR_WHITE],
                'size' => 14,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $worksheet->getStyle('A2:B2')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => self::ContextBlue],
            ],
            'font' => ['bold' => true, 'color' => ['rgb' => '1F2937']],
        ]);

        foreach ($columns as $columnIndex => $column) {
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex + 1);
            $worksheet->setCellValue("{$columnLetter}4", $column['heading']);
            $worksheet->getColumnDimension($columnLetter)->setWidth($column['width']);
        }

        $worksheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => self::BrandNavy],
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => Color::COLOR_WHITE],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $worksheet->getRowDimension(1)->setRowHeight(24);
        $worksheet->getRowDimension(4)->setRowHeight(28);
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $records
     * @param  list<array{field: string, heading: string, type: 'text'|'integer'|'hours'|'date', width: int}>  $columns
     */
    private function writeSpreadsheetRows(
        Worksheet $worksheet,
        Collection $records,
        array $columns,
    ): void {
        foreach ($records as $rowIndex => $record) {
            $excelRow = $rowIndex + 5;

            foreach ($columns as $columnIndex => $column) {
                $cell = Coordinate::stringFromColumnIndex($columnIndex + 1).$excelRow;
                $value = data_get($record, $column['field']);

                if (in_array($column['type'], ['integer', 'hours'], true)) {
                    $worksheet->setCellValue($cell, (float) $value);
                    $worksheet->getStyle($cell)->getNumberFormat()->setFormatCode(
                        $column['type'] === 'hours' ? '#,##0.00' : '#,##0',
                    );
                    $worksheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    continue;
                }

                $worksheet->setCellValueExplicit(
                    $cell,
                    $this->spreadsheetValue($value),
                    DataType::TYPE_STRING,
                );
                $worksheet->getStyle($cell)->getAlignment()->setHorizontal(
                    $column['type'] === 'date'
                        ? Alignment::HORIZONTAL_CENTER
                        : Alignment::HORIZONTAL_LEFT,
                );
            }

            $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
            $worksheet->getStyle("A{$excelRow}:{$lastColumn}{$excelRow}")
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(Border::BORDER_HAIR)
                ->setColor(new Color(self::GridGrey));
            $worksheet->getStyle("A{$excelRow}:{$lastColumn}{$excelRow}")
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    private function configureSpreadsheetPrintLayout(
        Worksheet $worksheet,
        int $columnCount,
    ): void {
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

        $worksheet->setShowGridlines(false);
        $worksheet->freezePane('A5');
        $worksheet->setAutoFilter("A4:{$lastColumn}4");
        $worksheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        $worksheet->getPageMargins()
            ->setTop(0.35)
            ->setRight(0.25)
            ->setBottom(0.35)
            ->setLeft(0.25);
    }

    private function filename(string $table, string $period, string $extension): string
    {
        return Str::slug(str_replace('/', '-', "{$table}-{$period}")).".{$extension}";
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
