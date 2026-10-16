<?php

namespace Database\Factories;

use App\Models\KompenResponHubActiveStudentImport;
use App\Models\KompenResponHubActiveStudentImportReference;
use App\Models\KompenResponHubAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KompenResponHubActiveStudentImportReference>
 */
class KompenResponHubActiveStudentImportReferenceFactory extends Factory
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
            'sikompen_active_student_import_id' => KompenResponHubActiveStudentImport::factory(),
            'activated_by_admin_id' => KompenResponHubAdmin::factory(),
            'activated_at' => now(),
        ];
    }
}
