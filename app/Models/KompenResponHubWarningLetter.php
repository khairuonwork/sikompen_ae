<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubWarningLetterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $cutoff_id
 * @property int|null $current_student_id
 * @property string $nim
 * @property string $periode_semester
 * @property string $kelas
 * @property string $nama_mahasiswa
 * @property string $classification
 * @property string $letter_status
 * @property string $resolution
 * @property array<string, int|float|string> $snapshot
 * @property string|null $reason
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_source
 * @property int|null $created_by_admin_id
 * @property int|null $updated_by_admin_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read KompenResponHubStudent|null $student
 * @property-read KompenResponHubPeriodCutoff|null $cutoff
 */
class KompenResponHubWarningLetter extends Model
{
    /** @use HasFactory<KompenResponHubWarningLetterFactory> */
    use HasFactory;

    public const LetterStatusNotCreated = 'not_created';

    public const LetterStatusDraft = 'draft';

    public const LetterStatusIssued = 'issued';

    public const LetterStatusCancelled = 'cancelled';

    public const CancellationSourceFinalizationRollback = 'period_finalization_rollback';

    public const CancellationSourceManualRollback = 'manual_rollback';

    protected $table = 'sikompen_surat_peringatan';

    protected $fillable = ['cutoff_id', 'current_student_id', 'nim', 'periode_semester', 'kelas', 'nama_mahasiswa', 'classification', 'letter_status', 'resolution', 'snapshot', 'reason', 'issued_at', 'cancelled_at', 'cancellation_source', 'created_by_admin_id', 'updated_by_admin_id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'issued_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
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

    /** @return BelongsTo<KompenResponHubPeriodCutoff, $this> */
    public function cutoff(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubPeriodCutoff::class, 'cutoff_id');
    }
}
