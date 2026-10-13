<?php

namespace Database\Factories;

use App\Models\KompenResponHubStudentPasswordResetRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KompenResponHubStudentPasswordResetRequest>
 */
class KompenResponHubStudentPasswordResetRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nim' => fake()->unique()->numerify('26########'),
            'reset_code' => 'ABCDEFG1',
            'attempts' => 0,
            'requested_at' => now(),
            'expires_at' => now()->addDay(),
            'fulfilled_at' => null,
        ];
    }
}
