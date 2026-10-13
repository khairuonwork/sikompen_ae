<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActiveImport;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubWarningLetter;
use Illuminate\Support\Facades\DB;

class ReleaseKompenResponHubImportVersion
{
    /**
     * Remove a workbook from the active source registry without changing the
     * operational student records or finalized warning-letter records.
     *
     * @return array{import_id: int, periode_semester: string, display_filename: string}
     */
    public function execute(
        KompenResponHubImport $import,
        string $actorName,
        string $actorEmail,
    ): array {
        return DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($actorEmail, $actorName, $import): array {
                $lockedImport = KompenResponHubImport::query()
                    ->lockForUpdate()
                    ->findOrFail($import->id);

                KompenResponHubPeriodCutoff::query()
                    ->where('periode_semester', $lockedImport->periode_semester)
                    ->lockForUpdate()
                    ->first();

                $activeImport = KompenResponHubActiveImport::query()
                    ->where('periode_semester', $lockedImport->periode_semester)
                    ->lockForUpdate()
                    ->first();

                if ($activeImport?->kompen_respon_hub_import_id !== $lockedImport->id) {
                    throw new \LogicException('Versi workbook ini tidak sedang digunakan.');
                }

                $hasActiveWarning = KompenResponHubWarningLetter::query()
                    ->where('periode_semester', $lockedImport->periode_semester)
                    ->where('classification', 'fixed')
                    ->where('letter_status', KompenResponHubWarningLetter::LetterStatusIssued)
                    ->where('resolution', 'outstanding')
                    ->lockForUpdate()
                    ->exists();

                if ($hasActiveWarning) {
                    throw new \LogicException('Workbook tidak dapat dilepas karena periode ini masih memiliki SP aktif. Rollback atau selesaikan SP terlebih dahulu.');
                }

                $activeImport->delete();

                $displayFilename = $lockedImport->display_filename ?? $lockedImport->original_filename;

                KompenResponHubImportAuditLog::query()->create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_RELEASE,
                    'import_type' => KompenResponHubImportAuditLog::ImportTypeKompenRespon,
                    'source_import_id' => $lockedImport->id,
                    'actor_name' => $actorName,
                    'actor_email' => $actorEmail,
                    'periode_semester' => $lockedImport->periode_semester,
                    'original_filename' => $displayFilename,
                    'class_count' => $lockedImport->class_count,
                    'student_count' => $lockedImport->student_count,
                    'detail_count' => $lockedImport->detail_count,
                    'metadata' => ['active_reference_removed' => true],
                    'occurred_at' => now(),
                ]);

                return [
                    'import_id' => $lockedImport->id,
                    'periode_semester' => $lockedImport->periode_semester,
                    'display_filename' => $displayFilename,
                ];
            });
    }
}
