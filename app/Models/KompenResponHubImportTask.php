<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubImportTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $uploaded_by_admin_id
 * @property string|null $uploader_name
 * @property string|null $uploader_email
 * @property string $original_filename
 * @property string $stored_path
 * @property string $file_hash
 * @property string $status
 * @property int $progress
 * @property string $progress_message
 * @property string|null $error_message
 * @property CarbonInterface|null $queued_at
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $failed_at
 * @property int|null $kompen_respon_hub_import_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read KompenResponHubImport|null $importBatch
 * @property-read KompenResponHubAdmin|null $uploadedByAdmin
 */
class KompenResponHubImportTask extends Model
{
    /** @use HasFactory<KompenResponHubImportTaskFactory> */
    use HasFactory;

    protected $table = 'sikompen_import_tasks';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uploaded_by_admin_id',
        'uploader_name',
        'uploader_email',
        'original_filename',
        'stored_path',
        'file_hash',
        'status',
        'progress',
        'progress_message',
        'error_message',
        'kompen_respon_hub_import_id',
        'queued_at',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return BelongsTo<KompenResponHubImport, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubImport::class, 'kompen_respon_hub_import_id');
    }

    /** @return BelongsTo<KompenResponHubAdmin, $this> */
    public function uploadedByAdmin(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubAdmin::class, 'uploaded_by_admin_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true);
    }
}
