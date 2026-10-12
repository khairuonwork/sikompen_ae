<?php

namespace Database\Factories;

use App\Models\KompenResponHubStudentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<KompenResponHubStudentAccount>
 */
class KompenResponHubStudentAccountFactory extends Factory
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
            'password' => Hash::make('password-yang-kuat'),
            'password_changed_at' => now(),
            'last_login_at' => null,
        ];
    }
}
