<?php

namespace Database\Factories;

use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubAdminPasswordResetCode;
use App\Models\KompenResponHubAdminPasswordResetRecipient;
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
            'recipient_id' => KompenResponHubAdminPasswordResetRecipient::factory(),
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
        ];
    }
}
