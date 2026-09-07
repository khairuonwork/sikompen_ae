<?php

namespace Database\Factories;

use App\Models\KompenResponHubAdminSetupWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KompenResponHubAdminSetupWindow>
 */
class KompenResponHubAdminSetupWindowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activation_code_hash' => null,
            'expires_at' => null,
            'opened_by_admin_id' => null,
        ];
    }
}
