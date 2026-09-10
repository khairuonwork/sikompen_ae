<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubImportAuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $event_type
 * @property int|null $source_import_id
 * @property string|null $actor_name
 * @property string|null $actor_email
 * @property string $periode_semester
 * @property string $original_filename
 * @property int $class_count
 * @property int $student_count
 * @property int $detail_count
 * @property CarbonInterface $occurred_at
 */
class KompenResponHubImportAuditLog extends Model
{
    /** @use HasFactory<KompenResponHubImportAuditLogFactory> */
    use HasFactory;

    protected $table = 'sikompen_import_audit_logs';

    public const EVENT_UPLOAD = 'upload';

    public const EVENT_ROLLBACK = 'rollback';

    protected $fillable = [
        'event_type',
        'source_import_id',
        'actor_name',
        'actor_email',
        'periode_semester',
        'original_filename',
        'class_count',
        'student_count',
        'detail_count',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'source_import_id' => 'integer',
            'class_count' => 'integer',
            'student_count' => 'integer',
            'detail_count' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return BelongsTo<KompenResponHubImport, $this> */
    public function sourceImport(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubImport::class, 'source_import_id');
    }
}
