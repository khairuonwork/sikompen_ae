<?php

namespace Database\Factories;

use App\Models\KompenResponHubAdminPasswordResetRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KompenResponHubAdminPasswordResetRecipient>
 */
class KompenResponHubAdminPasswordResetRecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->userName().'@gmail.com',
            'added_by_admin_id' => null,
        ];
    }
}
