<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubStudent;
use Illuminate\Support\Facades\DB;

class ImportKompenResponHubWorkbook
{
    /**
     * @param  array{preview: array{periode_semester: ?string, classes: list<string>, class_count: int, student_count: int, detail_count: int}, students: list<array<string, mixed>>, details: list<array<string, mixed>>}  $payload
     * @return array{import_id: int, class_count: int, student_count: int, detail_count: int}
     */
    public function execute(
        array $payload,
        string $originalFilename,
        string $storedPath,
        string $fileHash,
        int $uploadedByAdminId,
        string $uploaderName,
        string $uploaderEmail,
    ): array {
        $preview = $payload['preview'];
        $period = $preview['periode_semester'];

        if ($period === null) {
            throw new \LogicException('Periode semester tidak ditemukan pada workbook.');
        }

        return DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($payload, $preview, $period, $originalFilename, $storedPath, $fileHash, $uploadedByAdminId, $uploaderName, $uploaderEmail): array {
                $import = KompenResponHubImport::create([
                    'uploaded_by_admin_id' => $uploadedByAdminId,
                    'uploader_name' => $uploaderName,
                    'uploader_email' => $uploaderEmail,
                    'periode_semester' => $period,
                    'original_filename' => $originalFilename,
                    'stored_path' => $storedPath,
                    'file_hash' => $fileHash,
                    'class_count' => $preview['class_count'],
                    'student_count' => $preview['student_count'],
                    'detail_count' => $preview['detail_count'],
                    'imported_at' => now(),
                ]);

                KompenResponHubStudent::query()
                    ->where('periode_semester', $period)
                    ->whereIn('kelas', $preview['classes'])
                    ->delete();

                $studentIds = [];
                foreach ($payload['students'] as $studentAttributes) {
                    $student = KompenResponHubStudent::create([
                        ...$studentAttributes,
                        'kompen_respon_hub_import_id' => $import->id,
                        'periode_semester' => $period,
                    ]);

                    $studentIds["{$student->kelas}:{$student->nim}"] = $student->id;
                }

                foreach ($payload['details'] as $detailAttributes) {
                    $studentKey = "{$detailAttributes['kelas']}:{$detailAttributes['nim']}";

                    DB::connection(config('kompen-respon-hub.database_connection'))
                        ->table('kompen_respon_hub_details')
                        ->insert([
                            'kompen_respon_hub_student_id' => $studentIds[$studentKey],
                            'tanggal' => $detailAttributes['tanggal'],
                            'mata_kuliah' => $detailAttributes['mata_kuliah'],
                            'nama_dosen' => $detailAttributes['nama_dosen'],
                            'jenis_pertemuan' => $detailAttributes['jenis_pertemuan'],
                            'presensi' => $detailAttributes['presensi'],
                            'menit_keterlambatan' => $detailAttributes['menit_keterlambatan'],
                            'keterangan' => $detailAttributes['keterangan'],
                            'jam_kompensasi' => $detailAttributes['jam_kompensasi'],
                            'jam_responsi' => $detailAttributes['jam_responsi'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                }

                return [
                    'import_id' => $import->id,
                    'class_count' => $preview['class_count'],
                    'student_count' => $preview['student_count'],
                    'detail_count' => $preview['detail_count'],
                ];
            });
    }
}
