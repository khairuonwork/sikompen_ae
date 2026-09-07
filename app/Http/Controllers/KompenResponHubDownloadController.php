<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Http\Requests\DownloadKompenResponHubDataRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KompenResponHubDownloadController extends Controller
{
    private const ExportLimit = 5000;

    public function __construct(private KompenResponHubDataQuery $dataQuery) {}

    public function students(DownloadKompenResponHubDataRequest $request): StreamedResponse
    {
        $filters = $request->validated();

        return $this->download(
            'kompen-dan-respon',
            $filters['periode_semester'],
            $this->dataQuery->students($filters)->limit(self::ExportLimit + 1)->get(),
            [
                'tingkat' => 'Tingkat',
                'nim' => 'NIM',
                'nama_mahasiswa' => 'Nama Mahasiswa',
                'kelas' => 'Kelas',
                'periode_semester' => 'Periode',
                'total_jam_terlambat' => 'T[j]',
                'total_jam_sakit' => 'S[j]',
                'total_jam_izin' => 'I[j]',
                'total_jam_bolos' => 'B[j]',
                'total_kompensasi_jam' => 'Kompensasi[j]',
                'total_responsi_jam' => 'Responsi[j]',
                'total_hutang_jam' => 'Total[j]',
                'kompensasi_dikerjakan_jam' => 'Dikerjakan[j]',
                'sisa_hutang_jam' => 'Sisa Kompen[j]',
            ],
        );
    }

    public function details(DownloadKompenResponHubDataRequest $request): StreamedResponse
    {
        $filters = $request->validated();

        return $this->download(
            'detail-kompen',
            $filters['periode_semester'],
            $this->dataQuery->details($filters)->limit(self::ExportLimit + 1)->get(),
            [
                'student.tingkat' => 'Tingkat',
                'student.nim' => 'NIM',
                'student.nama_mahasiswa' => 'Nama Mahasiswa',
                'student.kelas' => 'Kelas',
                'student.periode_semester' => 'Periode',
                'mata_kuliah' => 'Mata Kuliah',
                'nama_dosen' => 'Nama Dosen',
                'tanggal' => 'Tanggal',
                'jenis_pertemuan' => 'Jenis Pertemuan',
                'presensi' => 'Presensi',
                'menit_keterlambatan' => 'Menit Keterlambatan',
                'keterangan' => 'Keterangan',
                'jam_kompensasi' => 'Jam Kompensasi',
                'jam_responsi' => 'Jam Responsi',
            ],
        );
    }

    /**
     * @param  Collection<int, Model>  $records
     * @param  array<string, string>  $columns
     */
    private function download(string $table, string $period, Collection $records, array $columns): StreamedResponse
    {
        abort_if(
            $records->count() > self::ExportLimit,
            422,
            'Hasil unduhan melebihi 5.000 baris. Tambahkan filter kelas, tingkat, atau nama.',
        );

        $rows = $records
            ->map(fn (Model $record): array => array_map(
                fn (string $field): string => $this->spreadsheetValue(data_get($record, $field)),
                array_keys($columns),
            ))
            ->all();
        $filename = Str::slug(str_replace('/', '-', "{$table}-{$period}")).'.xlsx';

        return response()->streamDownload(function () use ($columns, $rows): void {
            $spreadsheet = new Spreadsheet;
            $worksheet = $spreadsheet->getActiveSheet();
            $worksheet->setTitle('Data Kompen');

            foreach (array_values($columns) as $columnIndex => $header) {
                $worksheet->setCellValue(
                    Coordinate::stringFromColumnIndex($columnIndex + 1).'1',
                    $header,
                );
            }

            foreach ($rows as $rowIndex => $row) {
                foreach ($row as $columnIndex => $value) {
                    $worksheet->setCellValueExplicit(
                        Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 2),
                        $value,
                        DataType::TYPE_STRING,
                    );
                }
            }

            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function spreadsheetValue(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
