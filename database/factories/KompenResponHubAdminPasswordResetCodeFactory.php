<?php

namespace Database\Factories;

use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubAdminPasswordResetCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<KompenResponHubAdminPasswordResetCode>
 */
class KompenResponHubAdminPasswordResetCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => KompenResponHubAdmin::factory(),
            'recipient_id' => null,
            'generated_by_admin_id' => null,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
        ];
    }
}
