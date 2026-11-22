<?php

namespace Database\Factories;

use App\Models\KompenResponHubWarningLetterArtifact;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KompenResponHubWarningLetterArtifact>
 */
class KompenResponHubWarningLetterArtifactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cutoff_id' => fake()->unique()->numberBetween(1, 9_999_999),
            'status' => KompenResponHubWarningLetterArtifact::StatusFinalized,
            'revision' => 1,
            'display_filename' => '2026-2027_Gasal_list_surat_peringatan.xlsx',
            'stored_path' => 'kompen-respon-hub/warning-artifacts/'.Str::uuid().'/artifact.xlsx',
            'file_hash' => hash('sha256', fake()->uuid()),
            'row_count' => fake()->numberBetween(0, 100),
            'finalized_at' => now(),
        ];
    }
}
