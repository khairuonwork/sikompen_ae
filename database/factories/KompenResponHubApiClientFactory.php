<?php

namespace Database\Factories;

use App\Models\KompenResponHubApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KompenResponHubApiClient>
 */
class KompenResponHubApiClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Str::lower(fake()->unique()->bothify('client-####')),
            'name' => fake()->company(),
            'token_hash' => hash('sha256', fake()->uuid()),
            'scopes' => ['kompen-respon:read'],
            'allowed_ips' => ['127.0.0.1'],
            'rate_limit_per_minute' => 30,
            'is_active' => true,
        ];
    }
}
