<?php

namespace App\Models;

use Database\Factories\KompenResponHubActiveStudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KompenResponHubActiveStudent extends Model
{
    /** @use HasFactory<KompenResponHubActiveStudentFactory> */
    use HasFactory;

    protected $table = 'sikompen_active_students';

    protected $fillable = [
        'sikompen_active_student_import_id',
        'nim',
        'nama_mahasiswa',
        'kelas',
    ];

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return BelongsTo<KompenResponHubActiveStudentImport, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubActiveStudentImport::class, 'sikompen_active_student_import_id');
    }
}
