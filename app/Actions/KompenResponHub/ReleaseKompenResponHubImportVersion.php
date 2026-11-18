<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActiveImport;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubWarningLetter;
use App\Models\KompenResponHubWarningPeriodSelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReleaseKompenResponHubImportVersion
{
    /** @return array{import_id: int, periode_semester: string, display_filename: string} */
    public function execute(
        KompenResponHubImport $import,
        string $actorName,
        string $actorUsername,
    ): array {
        return DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($actorUsername, $actorName, $import): array {
                $lockedImport = KompenResponHubImport::query()
                    ->lockForUpdate()
                    ->findOrFail($import->id);

                $cutoff = KompenResponHubPeriodCutoff::query()
                    ->where('periode_semester', $lockedImport->periode_semester)
                    ->lockForUpdate()
                    ->first();

                if ($cutoff?->closed_at !== null) {
                    throw new \LogicException('Workbook tidak dapat dilepas karena periode SP sudah difinalisasi. Mulai koreksi periode SP terlebih dahulu.');
                }

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
                    throw new \LogicException('Workbook tidak dapat dilepas karena periode ini masih memiliki SP aktif. Mulai koreksi periode SP terlebih dahulu.');
                }

                $isSelectedWarningPeriod = KompenResponHubWarningPeriodSelection::query()
                    ->where('selection_key', KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey)
                    ->whereHas('cutoff', fn (Builder $cutoffQuery): Builder => $cutoffQuery->where('periode_semester', $lockedImport->periode_semester))
                    ->lockForUpdate()
                    ->exists();

                if ($isSelectedWarningPeriod) {
                    throw new \LogicException('Workbook tidak dapat dilepas karena periodenya sedang dipilih sebagai periode SP aktif. Pilih periode SP aktif lain terlebih dahulu.');
                }

                $activeImport->delete();

                $displayFilename = $lockedImport->display_filename ?? $lockedImport->original_filename;

                KompenResponHubImportAuditLog::query()->create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_RELEASE,
                    'import_type' => KompenResponHubImportAuditLog::ImportTypeKompenRespon,
                    'source_import_id' => $lockedImport->id,
                    'actor_name' => $actorName,
                    'actor_username' => $actorUsername,
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
