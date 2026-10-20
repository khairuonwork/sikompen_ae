<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubApiSyncRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $api_client_id
 * @property string|null $api_client_key
 * @property string $run_id
 * @property string $idempotency_key
 * @property string $selector_type
 * @property string $periode_semester
 * @property string|null $kelas
 * @property string $status
 * @property string|null $artifact_path
 * @property string|null $artifact_checksum
 * @property int|null $record_count
 * @property int|null $artifact_size_bytes
 * @property string|null $failure_code
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $snapshot_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read KompenResponHubApiClient $client
 */
class KompenResponHubApiSyncRun extends Model
{
    /** @use HasFactory<KompenResponHubApiSyncRunFactory> */
    use HasFactory;

    protected $fillable = [
        'api_client_id',
        'api_client_key',
        'run_id',
        'idempotency_key',
        'selector_type',
        'periode_semester',
        'kelas',
        'status',
        'artifact_path',
        'artifact_checksum',
        'record_count',
        'artifact_size_bytes',
        'failure_code',
        'started_at',
        'snapshot_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'record_count' => 'integer',
            'artifact_size_bytes' => 'integer',
            'started_at' => 'immutable_datetime',
            'snapshot_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return BelongsTo<KompenResponHubApiClient, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubApiClient::class, 'api_client_id');
    }
}
