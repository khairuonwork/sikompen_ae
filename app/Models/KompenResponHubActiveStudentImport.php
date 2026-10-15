<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubActiveStudentImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $uploaded_by_admin_id
 * @property string $uploader_name
 * @property string|null $uploader_username
 * @property string $original_filename
 * @property string|null $periode_semester
 * @property string $stored_path
 * @property string $file_hash
 * @property int $student_count
 * @property int $class_count
 * @property CarbonInterface $imported_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
class KompenResponHubActiveStudentImport extends Model
{
    /** @use HasFactory<KompenResponHubActiveStudentImportFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'sikompen_active_student_imports';

    protected $fillable = [
        'uploaded_by_admin_id',
        'uploader_name',
        'uploader_username',
        'original_filename',
        'periode_semester',
        'stored_path',
        'file_hash',
        'student_count',
        'class_count',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'student_count' => 'integer',
            'class_count' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return BelongsTo<KompenResponHubAdmin, $this> */
    public function uploadedByAdmin(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubAdmin::class, 'uploaded_by_admin_id');
    }

    /** @return HasMany<KompenResponHubActiveStudent, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(KompenResponHubActiveStudent::class, 'sikompen_active_student_import_id');
    }

    /** @return HasMany<KompenResponHubActiveStudentImportSource, $this> */
    public function activeSources(): HasMany
    {
        return $this->hasMany(KompenResponHubActiveStudentImportSource::class, 'sikompen_active_student_import_id');
    }

    /** @return HasOne<KompenResponHubActiveStudentImportReference, $this> */
    public function activeReference(): HasOne
    {
        return $this->hasOne(KompenResponHubActiveStudentImportReference::class, 'sikompen_active_student_import_id');
    }

    /** @return HasMany<KompenResponHubImportAuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(KompenResponHubImportAuditLog::class, 'source_active_student_import_id');
    }
}
