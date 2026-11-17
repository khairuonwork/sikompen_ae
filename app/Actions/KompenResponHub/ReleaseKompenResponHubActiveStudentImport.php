<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActiveStudentImport;
use App\Models\KompenResponHubActiveStudentImportReference;
use App\Models\KompenResponHubImportAuditLog;
use Illuminate\Support\Facades\DB;

class ReleaseKompenResponHubActiveStudentImport
{
    /**
     * @return array{import_id: int, original_filename: string, periode_semester: string, class_count: int, student_count: int}
     */
    public function execute(
        KompenResponHubActiveStudentImport $import,
        string $actorName,
        string $actorUsername,
    ): array {
        return DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($actorName, $actorUsername, $import): array {
            $lockedImport = KompenResponHubActiveStudentImport::query()
                ->lockForUpdate()
                ->findOrFail($import->id);
            $reference = KompenResponHubActiveStudentImportReference::query()
                ->where('selection_key', KompenResponHubActiveStudentImportReference::StudentLoginSelectionKey)
                ->lockForUpdate()
                ->first();

            if ($reference?->sikompen_active_student_import_id !== $lockedImport->id) {
                throw new \LogicException('Hanya daftar mahasiswa aktif yang sedang digunakan dapat dinonaktifkan.');
            }

            $reference->delete();

            KompenResponHubImportAuditLog::query()->create([
                'event_type' => KompenResponHubImportAuditLog::EVENT_RELEASE,
                'import_type' => KompenResponHubImportAuditLog::ImportTypeActiveStudents,
                'source_active_student_import_id' => $lockedImport->id,
                'actor_name' => $actorName,
                'actor_username' => $actorUsername,
                'periode_semester' => $lockedImport->periode_semester,
                'original_filename' => $lockedImport->original_filename,
                'class_count' => $lockedImport->class_count,
                'student_count' => $lockedImport->student_count,
                'detail_count' => 0,
                'metadata' => ['student_login_access_disabled' => true],
                'occurred_at' => now(),
            ]);

            return [
                'import_id' => $lockedImport->id,
                'original_filename' => $lockedImport->original_filename,
                'periode_semester' => (string) $lockedImport->periode_semester,
                'class_count' => $lockedImport->class_count,
                'student_count' => $lockedImport->student_count,
            ];
        });
    }
}
