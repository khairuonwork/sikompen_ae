<?php

namespace App\Models;

use Database\Factories\KompenResponHubStudentProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KompenResponHubStudentProgress extends Model
{
    /** @use HasFactory<KompenResponHubStudentProgressFactory> */
    use HasFactory;

    protected $table = 'sikompen_mahasiswa_progress';

    protected $fillable = ['current_student_id', 'nim', 'periode_semester', 'kelas', 'kompensasi_dikerjakan_jam', 'responsi_dikerjakan_jam', 'last_worked_at', 'reason', 'updated_by_admin_id'];

    protected function casts(): array
    {
        return [
            'kompensasi_dikerjakan_jam' => 'decimal:4',
            'responsi_dikerjakan_jam' => 'decimal:4',
            'last_worked_at' => 'immutable_datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return BelongsTo<KompenResponHubStudent, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubStudent::class, 'current_student_id');
    }
}
