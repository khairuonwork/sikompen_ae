<?php

namespace App\Models;

use Database\Factories\KompenResponHubActiveStudentImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KompenResponHubActiveStudentImport extends Model
{
    /** @use HasFactory<KompenResponHubActiveStudentImportFactory> */
    use HasFactory;

    protected $table = 'sikompen_active_student_imports';

    protected $fillable = [
        'uploaded_by_admin_id',
        'uploader_name',
        'uploader_email',
        'original_filename',
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

    /** @return HasMany<KompenResponHubImportAuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(KompenResponHubImportAuditLog::class, 'source_active_student_import_id');
    }
}
