<?php

namespace App\Models;

use Database\Factories\KompenResponHubExportTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KompenResponHubExportTask extends Model
{
    /** @use HasFactory<KompenResponHubExportTaskFactory> */
    use HasFactory;

    public const StatusQueued = 'queued';

    public const StatusProcessing = 'processing';

    public const StatusCompleted = 'completed';

    public const StatusFailed = 'failed';

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
}
