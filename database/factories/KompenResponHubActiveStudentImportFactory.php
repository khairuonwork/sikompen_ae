<?php

namespace Database\Factories;

use App\Models\KompenResponHubActiveStudentImport;
use App\Models\KompenResponHubAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KompenResponHubActiveStudentImport>
 */
class KompenResponHubActiveStudentImportFactory extends Factory
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
            'original_filename' => 'mahasiswa-aktif.xlsx',
            'periode_semester' => '2027/2028 Gasal',
            'stored_path' => 'kompen-respon-hub/imports/active-students/source.xlsx',
            'file_hash' => fake()->sha256(),
            'student_count' => 25,
            'class_count' => 1,
            'imported_at' => now(),
        ];
    }
}
