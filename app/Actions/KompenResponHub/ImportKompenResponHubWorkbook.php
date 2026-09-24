<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubStudentProgress;
use App\Models\KompenResponHubStudentSummaryOverride;
use App\Models\KompenResponHubWarningLetter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ImportKompenResponHubWorkbook
{
    /**
     * @param  array{preview: array{periode_semester: ?string, classes: list<string>, class_count: int, student_count: int, detail_count: int}, students: list<array<string, mixed>>, details: list<array<string, mixed>>}  $payload
     * @return array{import_id: int, periode_semester: string, class_count: int, student_count: int, detail_count: int}
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

        $result = DB::connection(config('kompen-respon-hub.database_connection'))
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
                    'quality_report' => $this->qualityReport($payload),
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
                    $sourceKey = hash('sha256', json_encode($detailAttributes, JSON_THROW_ON_ERROR));

                    DB::connection(config('kompen-respon-hub.database_connection'))
                        ->table('sikompen_detail_kompen')
                        ->insert([
                            'kompen_respon_hub_student_id' => $studentIds[$studentKey],
                            'source_key' => $sourceKey,
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

                foreach ($payload['students'] as $studentAttributes) {
                    $studentKey = "{$studentAttributes['kelas']}:{$studentAttributes['nim']}";
                    $identity = [
                        'nim' => $studentAttributes['nim'],
                        'periode_semester' => $period,
                        'kelas' => $studentAttributes['kelas'],
                    ];
                    $currentStudentId = $studentIds[$studentKey];

                    KompenResponHubStudentProgress::query()->where($identity)->update(['current_student_id' => $currentStudentId]);
                    KompenResponHubStudentSummaryOverride::query()->where($identity)->update(['current_student_id' => $currentStudentId]);
                    KompenResponHubWarningLetter::query()->where($identity)->update(['current_student_id' => $currentStudentId]);
                }

                KompenResponHubImportAuditLog::create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_UPLOAD,
                    'source_import_id' => $import->id,
                    'actor_name' => $uploaderName,
                    'actor_email' => $uploaderEmail,
                    'periode_semester' => $period,
                    'original_filename' => $originalFilename,
                    'class_count' => $preview['class_count'],
                    'student_count' => $preview['student_count'],
                    'detail_count' => $preview['detail_count'],
                    'occurred_at' => now(),
                ]);

                return [
                    'import_id' => $import->id,
                    'periode_semester' => $period,
                    'class_count' => $preview['class_count'],
                    'student_count' => $preview['student_count'],
                    'detail_count' => $preview['detail_count'],
                ];
            });

        Cache::forever(
            KompenResponHubDataQuery::FILTER_OPTIONS_CACHE_VERSION_KEY,
            (int) Cache::get(KompenResponHubDataQuery::FILTER_OPTIONS_CACHE_VERSION_KEY, 1) + 1,
        );

        return $result;
    }

    /**
     * Persist a concise, human-readable validation result with the import.
     * The parser has already rejected malformed workbooks before this action
     * executes, so every check recorded here represents accepted source data.
     *
     * @param  array{preview: array{periode_semester: ?string, classes: list<string>, class_count: int, student_count: int, detail_count: int}, students: list<array<string, mixed>>, details: list<array<string, mixed>>}  $payload
     * @return array{status: string, checks: list<array{label: string, status: string, detail: string}>}
     */
    private function qualityReport(array $payload): array
    {
        $preview = $payload['preview'];
        $studentsWithDebt = collect($payload['students'])
            ->filter(fn (array $student): bool => (float) $student['total_hutang_jam'] > 0)
            ->count();

        return [
            'status' => 'passed',
            'checks' => [
                [
                    'label' => 'Struktur workbook',
                    'status' => 'passed',
                    'detail' => 'Sheet, kolom wajib, dan periode berhasil dibaca.',
                ],
                [
                    'label' => 'Identitas mahasiswa',
                    'status' => 'passed',
                    'detail' => sprintf('%d mahasiswa pada %d kelas siap diimpor.', $preview['student_count'], $preview['class_count']),
                ],
                [
                    'label' => 'Detail Kompen',
                    'status' => 'passed',
                    'detail' => sprintf('%d detail Kompen/Responsi tervalidasi.', $preview['detail_count']),
                ],
                [
                    'label' => 'Ringkasan hutang',
                    'status' => 'passed',
                    'detail' => sprintf('%d mahasiswa memiliki jam Kompen atau Responsi.', $studentsWithDebt),
                ],
            ],
        ];
    }
}
