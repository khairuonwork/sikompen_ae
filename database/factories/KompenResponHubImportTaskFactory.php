<?php

namespace Database\Factories;

use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImportTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KompenResponHubImportTask>
 */
class KompenResponHubImportTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uploaded_by_admin_id' => KompenResponHubAdmin::factory(),
            'uploader_name' => fake()->name(),
            'uploader_email' => fake()->unique()->safeEmail(),
            'original_filename' => 'kompen-respon.xlsx',
            'stored_path' => 'kompen-respon-hub/import-tasks/source.xlsx',
            'file_hash' => fake()->sha256(),
            'status' => KompenResponHubImportTask::STATUS_QUEUED,
            'progress' => 0,
            'progress_message' => 'Menunggu diproses.',
            'queued_at' => now(),
        ];
    }
}
