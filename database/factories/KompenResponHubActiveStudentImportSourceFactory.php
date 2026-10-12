<?php

namespace Database\Factories;

use App\Models\KompenResponHubActiveStudentImport;
use App\Models\KompenResponHubActiveStudentImportSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KompenResponHubActiveStudentImportSource>
 */
class KompenResponHubActiveStudentImportSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'periode_semester' => '2027/2028 Gasal',
            'kelas' => '1AEA1',
            'sikompen_active_student_import_id' => KompenResponHubActiveStudentImport::factory(),
            'activated_by_admin_id' => null,
            'activated_at' => now(),
        ];
    }
}
