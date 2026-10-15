<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubActiveStudentImport;
use App\Models\KompenResponHubImportAuditLog;
use Illuminate\Support\Facades\DB;

class ImportKompenResponHubActiveStudents
{
    /**
     * @param  array{errors: list<array{location: string, message: string}>, students: list<array{nim: string, nama_mahasiswa: string, kelas: string}>, preview: array{periode_semester: ?string, student_count: int, class_count: int}}  $payload
     * @return array{import_id: int, periode_semester: string, student_count: int, class_count: int}
     */
    public function execute(
        array $payload,
        string $originalFilename,
        string $storedPath,
        string $fileHash,
        int $uploadedByAdminId,
        string $uploaderName,
        string $uploaderUsername,
    ): array {
        $period = $payload['preview']['periode_semester'];

        if (! is_string($period) || $period === '') {
            throw new \LogicException('Periode daftar mahasiswa aktif tidak dapat ditentukan dari workbook.');
        }

        return DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($fileHash, $originalFilename, $payload, $period, $storedPath, $uploadedByAdminId, $uploaderUsername, $uploaderName): array {
                $import = KompenResponHubActiveStudentImport::create([
                    'uploaded_by_admin_id' => $uploadedByAdminId,
                    'uploader_name' => $uploaderName,
                    'uploader_username' => $uploaderUsername,
                    'original_filename' => $originalFilename,
                    'periode_semester' => $period,
                    'stored_path' => $storedPath,
                    'file_hash' => $fileHash,
                    'student_count' => $payload['preview']['student_count'],
                    'class_count' => $payload['preview']['class_count'],
                    'imported_at' => now(),
                ]);

                foreach (array_chunk($payload['students'], 500) as $students) {
                    KompenResponHubActiveStudent::query()->insert(
                        array_map(
                            fn (array $student): array => [
                                'sikompen_active_student_import_id' => $import->id,
                                'nim' => $student['nim'],
                                'nama_mahasiswa' => $student['nama_mahasiswa'],
                                'kelas' => $student['kelas'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ],
                            $students,
                        ),
                    );
                }

                KompenResponHubImportAuditLog::create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_UPLOAD,
                    'import_type' => KompenResponHubImportAuditLog::ImportTypeActiveStudents,
                    'source_active_student_import_id' => $import->id,
                    'actor_name' => $uploaderName,
                    'actor_username' => $uploaderUsername,
                    'periode_semester' => $period,
                    'original_filename' => $originalFilename,
                    'class_count' => $payload['preview']['class_count'],
                    'student_count' => $payload['preview']['student_count'],
                    'detail_count' => 0,
                    'occurred_at' => now(),
                ]);

                return [
                    'import_id' => $import->id,
                    'periode_semester' => $period,
                    'student_count' => $payload['preview']['student_count'],
                    'class_count' => $payload['preview']['class_count'],
                ];
            });
    }
}
