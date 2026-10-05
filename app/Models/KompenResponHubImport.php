<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KompenResponHubImport extends Model
{
    protected $table = 'sikompen_imports';

    protected $fillable = [
        'uploaded_by_admin_id',
        'uploader_name',
        'uploader_email',
        'periode_semester',
        'original_filename',
        'stored_path',
        'file_hash',
        'class_count',
        'student_count',
        'detail_count',
        'quality_report',
        'replaced_imports',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'quality_report' => 'array',
            'replaced_imports' => 'array',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    public function students(): HasMany
    {
        return $this->hasMany(KompenResponHubStudent::class);
    }

    public function uploadedByAdmin(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubAdmin::class, 'uploaded_by_admin_id');
    }

    /** @return HasOne<KompenResponHubActiveImport, $this> */
    public function activeReference(): HasOne
    {
        return $this->hasOne(KompenResponHubActiveImport::class, 'kompen_respon_hub_import_id');
    }
}
