<?php

namespace Database\Factories;

use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubActiveStudentImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KompenResponHubActiveStudent>
 */
class KompenResponHubActiveStudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sikompen_active_student_import_id' => KompenResponHubActiveStudentImport::factory(),
            'nim' => fake()->unique()->numerify('#########'),
            'nama_mahasiswa' => fake()->name(),
            'kelas' => '1AEA1',
        ];
    }
}
