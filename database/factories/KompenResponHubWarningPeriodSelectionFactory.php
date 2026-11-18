<?php

namespace Database\Factories;

use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubWarningPeriodSelection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KompenResponHubWarningPeriodSelection>
 */
class KompenResponHubWarningPeriodSelectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'selection_key' => KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey,
            'sikompen_period_cutoff_id' => KompenResponHubPeriodCutoff::factory(),
            'selected_by_admin_id' => KompenResponHubAdmin::factory(),
            'selected_at' => now(),
        ];
    }
}
