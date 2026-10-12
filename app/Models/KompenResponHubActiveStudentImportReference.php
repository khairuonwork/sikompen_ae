<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubActiveStudentImportReferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $periode_semester
 * @property int $sikompen_active_student_import_id
 * @property int|null $activated_by_admin_id
 * @property CarbonInterface $activated_at
 * @property-read KompenResponHubActiveStudentImport $importBatch
 * @property-read KompenResponHubAdmin|null $activatedByAdmin
 */
class KompenResponHubActiveStudentImportReference extends Model
{
    /** @use HasFactory<KompenResponHubActiveStudentImportReferenceFactory> */
    use HasFactory;

    protected $table = 'sikompen_active_student_import_references';

    protected $fillable = [
        'periode_semester',
        'sikompen_active_student_import_id',
        'activated_by_admin_id',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'sikompen_active_student_import_id' => 'integer',
            'activated_by_admin_id' => 'integer',
            'activated_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return BelongsTo<KompenResponHubActiveStudentImport, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubActiveStudentImport::class, 'sikompen_active_student_import_id');
    }

    /** @return BelongsTo<KompenResponHubAdmin, $this> */
    public function activatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubAdmin::class, 'activated_by_admin_id');
    }
}
