<?php

namespace Database\Factories;

use App\Models\KompenResponHubActiveStudentImportTask;
use App\Models\KompenResponHubAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KompenResponHubActiveStudentImportTask>
 */
class KompenResponHubActiveStudentImportTaskFactory extends Factory
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
            'uploader_username' => fake()->unique()->userName(),
            'original_filename' => 'mahasiswa-aktif.xlsx',
            'stored_path' => 'kompen-respon-hub/active-student-import-tasks/source.xlsx',
            'file_hash' => fake()->sha256(),
            'status' => KompenResponHubActiveStudentImportTask::StatusQueued,
            'progress' => 0,
            'progress_message' => 'Menunggu diproses.',
            'queued_at' => now(),
        ];
    }
}
