<?php

namespace App\Models;

use Database\Factories\KompenResponHubWarningLetterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KompenResponHubWarningLetter extends Model
{
    /** @use HasFactory<KompenResponHubWarningLetterFactory> */
    use HasFactory;

    public const LetterStatusNotCreated = 'not_created';

    public const LetterStatusDraft = 'draft';

    public const LetterStatusIssued = 'issued';

    public const LetterStatusCancelled = 'cancelled';

    protected $table = 'sikompen_surat_peringatan';

    protected $fillable = ['cutoff_id', 'current_student_id', 'nim', 'periode_semester', 'kelas', 'nama_mahasiswa', 'classification', 'letter_status', 'resolution', 'snapshot', 'reason', 'issued_at', 'cancelled_at', 'created_by_admin_id', 'updated_by_admin_id'];

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
