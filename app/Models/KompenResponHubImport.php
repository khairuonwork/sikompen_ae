<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int|null $uploaded_by_admin_id
 * @property string|null $uploader_name
 * @property string|null $uploader_email
 * @property string $periode_semester
 * @property string $original_filename
 * @property string $display_filename
 * @property string $stored_path
 * @property string $file_hash
 * @property int $class_count
 * @property int $student_count
 * @property int $detail_count
 * @property array<string, mixed>|null $quality_report
 * @property array<int, mixed>|null $replaced_imports
 * @property CarbonInterface $imported_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, KompenResponHubStudent> $students
 * @property-read KompenResponHubAdmin|null $uploadedByAdmin
 * @property-read KompenResponHubActiveImport|null $activeReference
 */
class KompenResponHubImport extends Model
{
    protected $table = 'sikompen_imports';

    protected $fillable = [
        'uploaded_by_admin_id',
        'uploader_name',
        'uploader_email',
        'periode_semester',
        'original_filename',
        'display_filename',
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

    /** @return HasMany<KompenResponHubStudent, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(KompenResponHubStudent::class);
    }

    /** @return BelongsTo<KompenResponHubAdmin, $this> */
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
