<?php

namespace App\Actions\KompenResponHub;

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
            $selection = KompenResponHubWarningPeriodSelection::query()
                ->where('selection_key', KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey)
                ->lockForUpdate()
                ->first();
            $changed = $selection?->sikompen_period_cutoff_id !== $lockedCutoff->id;

            if ($selection === null) {
                KompenResponHubWarningPeriodSelection::query()->create([
                    'selection_key' => KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey,
                    'sikompen_period_cutoff_id' => $lockedCutoff->id,
                    'selected_by_admin_id' => $adminId,
                    'selected_at' => now(),
                ]);
            } elseif ($changed) {
                $selection->update([
                    'sikompen_period_cutoff_id' => $lockedCutoff->id,
                    'selected_by_admin_id' => $adminId,
                    'selected_at' => now(),
                ]);
            }

            return ['cutoff' => $lockedCutoff, 'changed' => $changed];
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
}
