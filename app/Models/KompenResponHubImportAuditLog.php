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
 * @property string $import_type
 * @property int|null $source_import_id
 * @property int|null $source_active_student_import_id
 * @property string|null $actor_name
 * @property string|null $actor_email
 * @property string|null $periode_semester
 * @property string $original_filename
 * @property int $class_count
 * @property int $student_count
 * @property int $detail_count
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface $occurred_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read KompenResponHubImport|null $sourceImport
 * @property-read KompenResponHubActiveStudentImport|null $sourceActiveStudentImport
 */
class KompenResponHubImportAuditLog extends Model
{
    /** @use HasFactory<KompenResponHubImportAuditLogFactory> */
    use HasFactory;

    protected $table = 'sikompen_import_audit_logs';

    public const EVENT_UPLOAD = 'upload';

    public const EVENT_ROLLBACK = 'rollback';

    public const EVENT_RESTORE = 'restore';

    public const EVENT_ACTIVATE = 'activate';

    public const EVENT_RELEASE = 'release';

    public const EVENT_RENAME = 'rename';

    public const EVENT_DELETE = 'delete';

    public const EVENT_PURGE = 'purge';

    public const ImportTypeKompenRespon = 'kompen_respon';

    public const ImportTypeActiveStudents = 'active_students';

    protected $fillable = [
        'event_type',
        'import_type',
        'source_import_id',
        'source_active_student_import_id',
        'actor_name',
        'actor_email',
        'periode_semester',
        'original_filename',
        'class_count',
        'student_count',
        'detail_count',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'source_import_id' => 'integer',
            'source_active_student_import_id' => 'integer',
            'class_count' => 'integer',
            'student_count' => 'integer',
            'detail_count' => 'integer',
            'metadata' => 'array',
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

    /** @return BelongsTo<KompenResponHubActiveStudentImport, $this> */
    public function sourceActiveStudentImport(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubActiveStudentImport::class, 'source_active_student_import_id');
    }
}
