<?php

use App\Jobs\GenerateKompenResponHubApiSyncArtifact;
use App\Models\KompenResponHubApiClient;
use App\Models\KompenResponHubApiSyncRun;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubStudent;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('the outbound API requires an active client token and allowed IP address', function (): void {
    outboundStudent();

    $this->getJson('/api/v1/mahasiswa/261000001?periode=2026%2F2027%20Gasal')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');

    [$client, $token] = outboundClient(['allowed_ips' => ['203.0.113.0/24']]);

    $this->withHeaders(outboundHeaders($client, $token))
        ->getJson('/api/v1/mahasiswa/261000001?periode=2026%2F2027%20Gasal')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'ip_not_allowed');
});

test('the outbound API returns only the approved Kompen and Respon summary for one NIM', function (): void {
    $student = outboundStudent();
    [$client, $token] = outboundClient();

    $this->withHeaders(outboundHeaders($client, $token))
        ->getJson("/api/v1/mahasiswa/{$student->nim}?periode=2026%2F2027%20Gasal")
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.identitas.nim', $student->nim)
        ->assertJsonPath('data.akademik.periode', '2026/2027 Gasal')
        ->assertJsonPath('data.kompen.total_jam', 2)
        ->assertJsonPath('data.respon.total_jam', 1)
        ->assertJsonMissingPath('data.warning')
        ->assertJsonMissingPath('data.details');
});

test('a bulk sync creates a single idempotent NDJSON job and artifact', function (): void {
    outboundStudent();
    outboundStudent(['nim' => '261000002', 'nama_mahasiswa' => 'Dina Mahasiswa']);
    [$client, $token] = outboundClient();
    Queue::fake();
    Storage::fake('local');

    $headers = [...outboundHeaders($client, $token), 'Idempotency-Key' => 'sync-2026-gasal-all'];
    $payload = [
        'selector' => ['type' => 'periode', 'periode' => '2026/2027 Gasal'],
        'format' => 'ndjson',
        'compression' => 'gzip',
    ];

    $first = $this->withHeaders($headers)->postJson('/api/v1/sync-runs', $payload)
        ->assertAccepted()
        ->assertJsonPath('data.status', 'queued');
    $runId = $first->json('data.sync_run_id');

    $this->withHeaders($headers)->postJson('/api/v1/sync-runs', $payload)
        ->assertOk()
        ->assertJsonPath('data.sync_run_id', $runId);

    expect(KompenResponHubApiSyncRun::query()->count())->toBe(1);
    Queue::assertPushed(GenerateKompenResponHubApiSyncArtifact::class, function (GenerateKompenResponHubApiSyncArtifact $job): bool {
        return $job->queue === 'api-sync';
    });

    $run = KompenResponHubApiSyncRun::query()->sole();
    (new GenerateKompenResponHubApiSyncArtifact($run->id))->handle();
    $run->refresh();

    expect($run->status)->toBe('ready')
        ->and($run->record_count)->toBe(2)
        ->and($run->artifact_checksum)->not->toBeNull()
        ->and($run->expires_at)->not->toBeNull();

    Storage::disk('local')->assertExists("kompen-respon-hub/api-sync/{$run->run_id}.ndjson.gz");

    $this->withHeaders(outboundHeaders($client, $token))
        ->getJson("/api/v1/sync-runs/{$run->run_id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.record_count', 2);

    $this->withHeaders(outboundHeaders($client, $token))
        ->get("/api/v1/sync-runs/{$run->run_id}/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/gzip');
});

test('a class sync requires a valid class selector', function (): void {
    outboundStudent();
    [$client, $token] = outboundClient();
    Queue::fake();

    $this->withHeaders([
        ...outboundHeaders($client, $token),
        'Idempotency-Key' => 'sync-2026-gasal-class',
    ])->postJson('/api/v1/sync-runs', [
        'selector' => ['type' => 'kelas', 'periode' => '2026/2027 Gasal'],
        'format' => 'ndjson',
        'compression' => 'gzip',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonFragment(['selector.kelas']);

    expect(KompenResponHubApiSyncRun::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('stalled API sync runs are failed and terminal records are purged on schedule', function (): void {
    config([
        'kompen-respon-hub.queue.stalled_task_minutes' => 15,
        'kompen-respon-hub.queue.queued_task_minutes' => 60,
        'kompen-respon-hub.api_sync.run_retention_days' => 7,
    ]);
    Storage::fake('local');
    $stalledRun = KompenResponHubApiSyncRun::factory()->create([
        'status' => 'processing',
        'started_at' => now()->subMinutes(16),
    ]);
    $expiredArtifactRun = KompenResponHubApiSyncRun::factory()->create([
        'status' => 'ready',
        'artifact_path' => 'kompen-respon-hub/api-sync/expired.ndjson.gz',
        'completed_at' => now()->subHour(),
        'expires_at' => now()->subMinute(),
    ]);
    $expiredTerminalRun = KompenResponHubApiSyncRun::factory()->create([
        'status' => 'failed',
        'artifact_path' => 'kompen-respon-hub/api-sync/failed.ndjson.gz',
        'completed_at' => now()->subDays(8),
    ]);
    Storage::disk('local')->put($expiredArtifactRun->artifact_path, 'artifact');
    Storage::disk('local')->put($expiredTerminalRun->artifact_path, 'artifact');

    $this->artisan('sikompen:reconcile-tasks')->assertSuccessful();
    $this->artisan('sikompen:purge-api-sync-artifacts')->assertSuccessful();

    expect($stalledRun->refresh()->status)->toBe('failed')
        ->and($stalledRun->failure_code)->toBe('processing_timeout')
        ->and($expiredArtifactRun->refresh()->status)->toBe('expired')
        ->and($expiredArtifactRun->artifact_path)->toBeNull()
        ->and(KompenResponHubApiSyncRun::query()->find($expiredTerminalRun->id))->toBeNull()
        ->and(Storage::disk('local')->exists('kompen-respon-hub/api-sync/expired.ndjson.gz'))->toBeFalse()
        ->and(Storage::disk('local')->exists('kompen-respon-hub/api-sync/failed.ndjson.gz'))->toBeFalse();
});

/** @param array<string, mixed> $attributes */
function outboundStudent(array $attributes = []): KompenResponHubStudent
{
    $import = KompenResponHubImport::query()->firstOrCreate(
        ['periode_semester' => '2026/2027 Gasal', 'file_hash' => str_repeat('a', 64)],
        [
            'original_filename' => 'api-source.xlsx',
            'stored_path' => 'kompen-respon-hub/imports/api-source.xlsx',
            'class_count' => 1,
            'student_count' => 2,
            'detail_count' => 0,
            'imported_at' => now(),
        ],
    );

    return KompenResponHubStudent::query()->create([
        'kompen_respon_hub_import_id' => $import->id,
        'nim' => '261000001',
        'periode_semester' => '2026/2027 Gasal',
        'nama_mahasiswa' => 'Rina Mahasiswa',
        'kelas' => '1AEA1',
        'tingkat' => 1,
        'total_jam_terlambat' => '0.0000',
        'total_jam_sakit' => '0.0000',
        'total_jam_izin' => '0.0000',
        'total_jam_bolos' => '0.0000',
        'total_kompensasi_jam' => '2.0000',
        'total_responsi_jam' => '1.0000',
        'total_hutang_jam' => '3.0000',
        'kompensasi_dikerjakan_jam' => '0.0000',
        'sisa_hutang_jam' => '3.0000',
        ...$attributes,
    ]);
}

/** @param array<string, mixed> $attributes
 * @return array{KompenResponHubApiClient, string}
 */
function outboundClient(array $attributes = []): array
{
    $token = 'outbound-test-token-'.fake()->uuid();
    $client = KompenResponHubApiClient::factory()->create([
        'token_hash' => hash('sha256', $token),
        ...$attributes,
    ]);

    return [$client, $token];
}

/** @return array<string, string> */
function outboundHeaders(KompenResponHubApiClient $client, string $token): array
{
    return [
        'Authorization' => "Bearer {$token}",
        'X-Client-Id' => $client->client_id,
    ];
}
