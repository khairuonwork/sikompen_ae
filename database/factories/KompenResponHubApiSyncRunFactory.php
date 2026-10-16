<?php

namespace Database\Factories;

use App\Models\KompenResponHubApiClient;
use App\Models\KompenResponHubApiSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KompenResponHubApiSyncRun>
 */
class KompenResponHubApiSyncRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'api_client_id' => KompenResponHubApiClient::factory(),
            'run_id' => (string) Str::ulid(),
            'idempotency_key' => (string) Str::uuid(),
            'selector_type' => 'periode',
            'periode_semester' => '2026/2027 Gasal',
            'status' => 'queued',
        ];
    }
}
