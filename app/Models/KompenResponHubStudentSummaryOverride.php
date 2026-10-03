<?php

namespace App\Models;

use Database\Factories\KompenResponHubStudentSummaryOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KompenResponHubStudentSummaryOverride extends Model
{
    /** @use HasFactory<KompenResponHubStudentSummaryOverrideFactory> */
    use HasFactory;

    protected $table = 'sikompen_mahasiswa_summary_overrides';

    protected $fillable = ['current_student_id', 'nim', 'periode_semester', 'kelas', 'total_kompensasi_jam', 'total_responsi_jam', 'reason', 'updated_by_admin_id'];

    protected function casts(): array
    {
        return [
            'total_kompensasi_jam' => 'decimal:4',
            'total_responsi_jam' => 'decimal:4',
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
