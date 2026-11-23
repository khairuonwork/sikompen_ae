<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubActiveStudentImport;
use App\Models\KompenResponHubActiveStudentImportReference;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubStudentPasswordResetRequest;
use Illuminate\Support\Facades\DB;

class ActivateKompenResponHubActiveStudentImport
{
    /**
     * @return array{import_id: int, original_filename: string, periode_semester: string|null, classes: list<string>, invalidated_reset_request_count: int}
     */
    public function execute(
        KompenResponHubActiveStudentImport $import,
        string $actorName,
        string $actorUsername,
        ?int $actorAdminId = null,
    ): array {
        return DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($actorAdminId, $actorUsername, $actorName, $import): array {
                $lockedImport = KompenResponHubActiveStudentImport::query()
                    ->lockForUpdate()
                    ->findOrFail($import->id);

                $period = $lockedImport->periode_semester;
                $referencePeriod = filled($period) ? $period : 'Tidak diketahui';

                $classes = KompenResponHubActiveStudent::query()
                    ->whereBelongsTo($lockedImport, 'importBatch')
                    ->select('kelas')
                    ->distinct()
                    ->orderBy('kelas')
                    ->lockForUpdate()
                    ->pluck('kelas')
                    ->map(static fn (mixed $kelas): string => (string) $kelas)
                    ->values()
                    ->all();

                $classes = array_values($classes);

                if ($classes === []) {
                    throw new \LogicException('Versi daftar mahasiswa aktif tidak memiliki data kelas untuk digunakan.');
                }

                $activeReference = KompenResponHubActiveStudentImportReference::query()
                    ->where('selection_key', KompenResponHubActiveStudentImportReference::StudentLoginSelectionKey)
                    ->lockForUpdate()
                    ->first();

                if ($activeReference === null) {
                    KompenResponHubActiveStudentImportReference::query()->create([
                        'selection_key' => KompenResponHubActiveStudentImportReference::StudentLoginSelectionKey,
                        'periode_semester' => $referencePeriod,
                        'sikompen_active_student_import_id' => $lockedImport->id,
                        'activated_by_admin_id' => $actorAdminId,
                        'activated_at' => now(),
                    ]);
                } else {
                    $activeReference->update([
                        'periode_semester' => $referencePeriod,
                        'sikompen_active_student_import_id' => $lockedImport->id,
                        'activated_by_admin_id' => $actorAdminId,
                        'activated_at' => now(),
                    ]);
                }

                $activeNims = KompenResponHubActiveStudent::query()
                    ->whereBelongsTo($lockedImport, 'importBatch')
                    ->pluck('nim')
                    ->all();
                $invalidatedResetRequestCount = KompenResponHubStudentPasswordResetRequest::query()
                    ->whereNull('fulfilled_at')
                    ->whereNotIn('nim', $activeNims)
                    ->delete();

                KompenResponHubImportAuditLog::create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_ACTIVATE,
                    'import_type' => KompenResponHubImportAuditLog::ImportTypeActiveStudents,
                    'source_active_student_import_id' => $lockedImport->id,
                    'actor_name' => $actorName,
                    'actor_username' => $actorUsername,
                    'periode_semester' => $period,
                    'original_filename' => $lockedImport->original_filename,
                    'class_count' => count($classes),
                    'student_count' => $lockedImport->student_count,
                    'detail_count' => 0,
                    'metadata' => [
                        'activated_classes' => $classes,
                        'invalidated_student_reset_request_count' => $invalidatedResetRequestCount,
                    ],
                    'occurred_at' => now(),
                ]);

                return [
                    'import_id' => $lockedImport->id,
                    'original_filename' => $lockedImport->original_filename,
                    'periode_semester' => $period,
                    'classes' => $classes,
                    'invalidated_reset_request_count' => $invalidatedResetRequestCount,
                ];
            });
    }
}
