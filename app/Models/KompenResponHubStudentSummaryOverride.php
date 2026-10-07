<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubStudentSummaryOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $current_student_id
 * @property string $nim
 * @property string $periode_semester
 * @property string $kelas
 * @property string $total_kompensasi_jam
 * @property string $total_responsi_jam
 * @property string $reason
 * @property int|null $updated_by_admin_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read KompenResponHubStudent|null $student
 */
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
