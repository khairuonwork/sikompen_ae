<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubExportTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $request_session_id
 * @property string $access_token
 * @property int|null $requested_by_admin_id
 * @property 'students'|'details'|'warnings' $resource
 * @property 'xlsx'|'pdf' $format
 * @property array<string, mixed> $filters
 * @property 'queued'|'processing'|'completed'|'failed'|'cancelled' $status
 * @property int $progress
 * @property string|null $progress_message
 * @property string|null $output_path
 * @property string|null $download_filename
 * @property string|null $error_message
 * @property CarbonImmutable|null $queued_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class KompenResponHubExportTask extends Model
{
    /** @use HasFactory<KompenResponHubExportTaskFactory> */
    use HasFactory;

    public const StatusQueued = 'queued';

    public const StatusProcessing = 'processing';

    public const StatusCompleted = 'completed';

    public const StatusFailed = 'failed';

    public const StatusCancelled = 'cancelled';

    protected $table = 'sikompen_export_tasks';

    protected $fillable = [
        'request_session_id',
        'access_token',
        'requested_by_admin_id',
        'resource',
        'format',
        'filters',
        'status',
        'progress',
        'progress_message',
        'output_path',
        'download_filename',
        'error_message',
        'queued_at',
        'started_at',
        'completed_at',
        'failed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'progress' => 'integer',
            'queued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::StatusQueued, self::StatusProcessing], true);
    }

    /** @return array{resource: string, format: string, filters: array<string, mixed>} */
    public function activityMetadata(): array
    {
        return [
            'resource' => $this->resource,
            'format' => $this->format,
            'filters' => $this->filters,
        ];
    }
}
