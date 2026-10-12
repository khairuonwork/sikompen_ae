<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubActiveStudentImport;
use App\Models\KompenResponHubImportAuditLog;
use Illuminate\Support\Facades\DB;

class ImportKompenResponHubActiveStudents
{
    /**
     * @param  array{errors: list<array{location: string, message: string}>, students: list<array{nim: string, nama_mahasiswa: string, kelas: string}>, preview: array{student_count: int, class_count: int}}  $payload
     * @return array{import_id: int, student_count: int, class_count: int}
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
        return DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($payload, $originalFilename, $storedPath, $fileHash, $uploadedByAdminId, $uploaderName, $uploaderEmail): array {
                $import = KompenResponHubActiveStudentImport::create([
                    'uploaded_by_admin_id' => $uploadedByAdminId,
                    'uploader_name' => $uploaderName,
                    'uploader_email' => $uploaderEmail,
                    'original_filename' => $originalFilename,
                    'stored_path' => $storedPath,
                    'file_hash' => $fileHash,
                    'student_count' => $payload['preview']['student_count'],
                    'class_count' => $payload['preview']['class_count'],
                    'imported_at' => now(),
                ]);

                KompenResponHubActiveStudent::query()->delete();

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
                    'actor_email' => $uploaderEmail,
                    'periode_semester' => null,
                    'original_filename' => $originalFilename,
                    'class_count' => $payload['preview']['class_count'],
                    'student_count' => $payload['preview']['student_count'],
                    'detail_count' => 0,
                    'occurred_at' => now(),
                ]);

                return [
                    'import_id' => $import->id,
                    'student_count' => $payload['preview']['student_count'],
                    'class_count' => $payload['preview']['class_count'],
                ];
            });
    }
}
