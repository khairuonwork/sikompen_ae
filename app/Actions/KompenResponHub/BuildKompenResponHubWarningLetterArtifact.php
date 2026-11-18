<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubWarningLetter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class BuildKompenResponHubWarningLetterArtifact
{
    /**
     * @param  Collection<int, KompenResponHubWarningLetter>  $warnings
     * @return array{display_filename: string, temporary_path: string, row_count: int}
     */
    public function execute(
        KompenResponHubPeriodCutoff $cutoff,
        Collection $warnings,
    ): array {
        $displayFilename = $this->displayFilename($cutoff->periode_semester);
        $temporaryPath = 'kompen-respon-hub/warning-artifacts/.pending/'.Str::uuid().'.xlsx';
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($temporaryPath));

        $spreadsheet = new Spreadsheet;

        try {
            $worksheet = $spreadsheet->getActiveSheet();
            $worksheet->setTitle('Surat Peringatan');
            $this->writeHeader($worksheet, $cutoff);

            foreach ($warnings->values() as $index => $warning) {
                $this->writeWarningRow($worksheet, $index + 5, $warning);
            }

            $lastRow = max(5, $warnings->count() + 4);
            $worksheet->setAutoFilter("A4:J{$lastRow}");
            $worksheet->freezePane('A5');
            $worksheet->setShowGridlines(false);
            $worksheet->getPageSetup()
                ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                ->setPaperSize(PageSetup::PAPERSIZE_A4)
                ->setFitToWidth(1)
                ->setFitToHeight(0)
                ->setRowsToRepeatAtTopByStartAndEnd(1, 4);
            $worksheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setBottom(0.35)->setLeft(0.25);

            (new Xlsx($spreadsheet))->save($disk->path($temporaryPath));
        } catch (Throwable $exception) {
            $disk->delete($temporaryPath);

            throw $exception;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return [
            'display_filename' => $displayFilename,
            'temporary_path' => $temporaryPath,
            'row_count' => $warnings->count(),
        ];
    }

    public function destinationPath(KompenResponHubPeriodCutoff $cutoff, int $revision, string $displayFilename): string
    {
        return "kompen-respon-hub/warning-artifacts/cutoff-{$cutoff->id}/revision-{$revision}/{$displayFilename}";
    }

    private function displayFilename(string $period): string
    {
        $period = str_replace('/', '-', $period);
        $period = preg_replace('/[^A-Za-z0-9_-]+/', '_', $period) ?? 'periode';
        $period = trim($period, '_');

        return "{$period}_list_surat_peringatan.xlsx";
    }

    private function writeHeader(Worksheet $worksheet, KompenResponHubPeriodCutoff $cutoff): void
    {
        $worksheet->mergeCells('A1:J1');
        $worksheet->setCellValue('A1', 'Daftar Surat Peringatan');
        $worksheet->setCellValue('A2', 'Periode');
        $worksheet->setCellValue('B2', $cutoff->periode_semester);
        $worksheet->setCellValue('A3', 'Difinalisasi');
        $worksheet->setCellValue('B3', now($cutoff->timezone)->format('d/m/Y H:i'));

        $headings = [
            'NIM',
            'Nama Mahasiswa',
            'Kelas',
            'Periode',
            'Kompen (Jam)',
            'Responsi (Jam)',
            'Sisa (Jam)',
            'Status SP',
            'Catatan',
            'Diterbitkan',
        ];

        foreach ($headings as $index => $heading) {
            $column = chr(65 + $index);
            $worksheet->setCellValue("{$column}4", $heading);
            $worksheet->getColumnDimension($column)->setWidth(match ($column) {
                'A' => 16,
                'B' => 28,
                'C' => 12,
                'D' => 20,
                'E', 'F', 'G' => 14,
                'H' => 14,
                'I' => 38,
                'J' => 20,
                default => 15,
            });
        }

        $worksheet->getStyle('A1:J1')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1F4E78']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $worksheet->getStyle('A2:B3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'DCE6F1']],
            'font' => ['bold' => true, 'color' => ['rgb' => '1F2937']],
        ]);
        $worksheet->getStyle('A4:J4')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1F4E78']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $worksheet->getRowDimension(1)->setRowHeight(24);
        $worksheet->getRowDimension(4)->setRowHeight(28);
    }

    private function writeWarningRow(Worksheet $worksheet, int $row, KompenResponHubWarningLetter $warning): void
    {
        $snapshot = $warning->snapshot;
        $values = [
            $warning->nim,
            $warning->nama_mahasiswa,
            $warning->kelas,
            $warning->periode_semester,
            (float) ($snapshot['total_kompensasi_jam'] ?? 0),
            (float) ($snapshot['total_responsi_jam'] ?? 0),
            (float) ($snapshot['sisa_hutang_jam'] ?? 0),
            'SP diterbitkan',
            $warning->reason ?? '',
            $warning->issued_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '',
        ];

        foreach ($values as $index => $value) {
            $column = chr(65 + $index);
            $cell = "{$column}{$row}";

            if (in_array($column, ['E', 'F', 'G'], true)) {
                $worksheet->setCellValue($cell, $value);
                $worksheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
                $worksheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                continue;
            }

            $worksheet->setCellValueExplicit($cell, $this->safeSpreadsheetText((string) $value), DataType::TYPE_STRING);
        }

        $worksheet->getStyle("A{$row}:J{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $worksheet->getStyle("B{$row}:I{$row}")->getAlignment()->setWrapText(true);
    }

    private function safeSpreadsheetText(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
