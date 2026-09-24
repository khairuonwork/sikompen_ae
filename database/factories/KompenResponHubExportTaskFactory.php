<?php

namespace Database\Factories;

use App\Models\KompenResponHubExportTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KompenResponHubExportTask>
 */
class KompenResponHubExportTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_session_id' => fake()->uuid(),
            'access_token' => bin2hex(random_bytes(32)),
            'resource' => 'students',
            'format' => 'xlsx',
            'filters' => [],
            'status' => KompenResponHubExportTask::StatusQueued,
            'progress' => 0,
            'progress_message' => 'Permintaan ekspor masuk ke antrean.',
            'queued_at' => now(),
            'expires_at' => now()->addDay(),
        ];
    }
}
