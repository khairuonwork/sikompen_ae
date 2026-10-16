<?php

namespace Database\Factories;

use App\Models\KompenResponHubSystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KompenResponHubSystemSetting>
 */
class KompenResponHubSystemSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_log_retention_days' => 90,
            'import_audit_log_retention_days' => 90,
        ];
    }
}
