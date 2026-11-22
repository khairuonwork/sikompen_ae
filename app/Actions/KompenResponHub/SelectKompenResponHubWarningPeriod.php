<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActiveImport;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubWarningPeriodSelection;
use Illuminate\Support\Facades\DB;

class SelectKompenResponHubWarningPeriod
{
    /**
     * @return array{cutoff: KompenResponHubPeriodCutoff, changed: bool}
     */
    public function execute(KompenResponHubPeriodCutoff $cutoff, ?int $adminId): array
    {
        return DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($adminId, $cutoff): array {
            $lockedCutoff = KompenResponHubPeriodCutoff::query()
                ->lockForUpdate()
                ->findOrFail($cutoff->id);

            return $this->selectLockedCutoff($lockedCutoff, $adminId);
        });
    }

    /**
     * @return array{cutoff: KompenResponHubPeriodCutoff, changed: bool}
     */
    public function selectWhenMissing(KompenResponHubPeriodCutoff $cutoff, ?int $adminId): array
    {
        return DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($adminId, $cutoff): array {
            $lockedCutoff = KompenResponHubPeriodCutoff::query()
                ->lockForUpdate()
                ->findOrFail($cutoff->id);
            $selection = KompenResponHubWarningPeriodSelection::query()
                ->where('selection_key', KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey)
                ->lockForUpdate()
                ->first();

            if ($selection !== null) {
                return ['cutoff' => $lockedCutoff, 'changed' => false];
            }

            KompenResponHubWarningPeriodSelection::query()->create([
                'selection_key' => KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey,
                'sikompen_period_cutoff_id' => $lockedCutoff->id,
                'selected_by_admin_id' => $adminId,
                'selected_at' => now(),
            ]);

            return ['cutoff' => $lockedCutoff, 'changed' => true];
        });
    }

    /**
     * Select an active workbook period for SP without making an admin define a
     * deadline. The cutoff record remains an internal lifecycle anchor for
     * warnings, revisions, artifacts, and rollback.
     *
     * @return array{cutoff: KompenResponHubPeriodCutoff, changed: bool, created: bool}
     */
    public function executeForActivePeriod(string $period, ?int $adminId): array
    {
        return DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($adminId, $period): array {
                $activeImport = KompenResponHubActiveImport::query()
                    ->where('periode_semester', $period)
                    ->lockForUpdate()
                    ->first();

                if (! $activeImport instanceof KompenResponHubActiveImport) {
                    throw new \LogicException('Periode SP hanya dapat dipilih ketika workbook Kompen dan Respon periode tersebut masih digunakan.');
                }

                $cutoff = KompenResponHubPeriodCutoff::query()
                    ->where('periode_semester', $period)
                    ->lockForUpdate()
                    ->first();

                if (! $cutoff instanceof KompenResponHubPeriodCutoff) {
                    $cutoff = KompenResponHubPeriodCutoff::query()->create([
                        'periode_semester' => $period,
                        'deadline_at' => now('Asia/Jakarta')->startOfMinute()->addMinutes(5),
                        'timezone' => 'Asia/Jakarta',
                        'updated_by_admin_id' => $adminId,
                    ]);
                    $created = true;
                } else {
                    $created = false;
                }

                $selection = $this->selectLockedCutoff($cutoff, $adminId);

                return [...$selection, 'created' => $created];
            });
    }

    /**
     * @return array{cutoff: KompenResponHubPeriodCutoff, changed: bool}
     */
    private function selectLockedCutoff(KompenResponHubPeriodCutoff $cutoff, ?int $adminId): array
    {
        $selection = KompenResponHubWarningPeriodSelection::query()
            ->where('selection_key', KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey)
            ->lockForUpdate()
            ->first();
        $changed = $selection?->sikompen_period_cutoff_id !== $cutoff->id;

        if ($selection === null) {
            KompenResponHubWarningPeriodSelection::query()->create([
                'selection_key' => KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey,
                'sikompen_period_cutoff_id' => $cutoff->id,
                'selected_by_admin_id' => $adminId,
                'selected_at' => now(),
            ]);
        } elseif ($changed) {
            $selection->update([
                'sikompen_period_cutoff_id' => $cutoff->id,
                'selected_by_admin_id' => $adminId,
                'selected_at' => now(),
            ]);
        }

        return ['cutoff' => $cutoff, 'changed' => $changed];
    }
}
