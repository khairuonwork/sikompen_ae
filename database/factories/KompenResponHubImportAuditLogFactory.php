<?php

namespace Database\Factories;

use App\Models\KompenResponHubImportAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KompenResponHubImportAuditLog>
 */
class KompenResponHubImportAuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_type' => KompenResponHubImportAuditLog::EVENT_UPLOAD,
            'source_import_id' => fake()->numberBetween(1, 10_000),
            'actor_name' => fake()->name(),
            'actor_email' => fake()->unique()->safeEmail(),
            'periode_semester' => '2026/2027 Gasal',
            'original_filename' => 'kompen-respon.xlsx',
            'class_count' => 1,
            'student_count' => 25,
            'detail_count' => 10,
            'occurred_at' => now(),
        ];
    }
}
