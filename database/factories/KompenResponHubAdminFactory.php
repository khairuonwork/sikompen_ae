<?php

namespace Database\Factories;

use App\Models\KompenResponHubAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<KompenResponHubAdmin>
 */
class KompenResponHubAdminFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ];
    }
}
