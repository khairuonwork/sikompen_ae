<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KompenResponHubStudent extends Model
{
    protected $table = 'sikompen_mahasiswa';

    protected $fillable = [
        'kompen_respon_hub_import_id',
        'nim',
        'periode_semester',
        'nama_mahasiswa',
        'kelas',
        'tingkat',
        'total_jam_terlambat',
        'total_jam_sakit',
        'total_jam_izin',
        'total_jam_bolos',
        'total_kompensasi_jam',
        'total_responsi_jam',
        'total_hutang_jam',
        'kompensasi_dikerjakan_jam',
        'sisa_hutang_jam',
    ];

    protected function casts(): array
    {
        return [
            'tingkat' => 'integer',
            'total_jam_terlambat' => 'decimal:4',
            'total_jam_sakit' => 'decimal:4',
            'total_jam_izin' => 'decimal:4',
            'total_jam_bolos' => 'decimal:4',
            'total_kompensasi_jam' => 'decimal:4',
            'total_responsi_jam' => 'decimal:4',
            'total_hutang_jam' => 'decimal:4',
            'kompensasi_dikerjakan_jam' => 'decimal:4',
            'sisa_hutang_jam' => 'decimal:4',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubImport::class, 'kompen_respon_hub_import_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(KompenResponHubDetail::class);
    }

    public function progress(): HasOne
    {
        return $this->hasOne(KompenResponHubStudentProgress::class, 'current_student_id');
    }

    public function summaryOverride(): HasOne
    {
        return $this->hasOne(KompenResponHubStudentSummaryOverride::class, 'current_student_id');
    }

    public function latestWarning(): HasOne
    {
        return $this->hasOne(KompenResponHubWarningLetter::class, 'current_student_id')->latestOfMany();
    }

    public function cutoff(): HasOne
    {
        return $this->hasOne(KompenResponHubPeriodCutoff::class, 'periode_semester', 'periode_semester');
    }

    public function getEffectiveTotalKompensasiJamAttribute(): string
    {
        return $this->summaryOverride?->total_kompensasi_jam ?? $this->total_kompensasi_jam;
    }

    public function getEffectiveTotalResponsiJamAttribute(): string
    {
        return $this->summaryOverride?->total_responsi_jam ?? $this->total_responsi_jam;
    }

    public function getEffectiveKompensasiDikerjakanJamAttribute(): string
    {
        return $this->progress?->kompensasi_dikerjakan_jam ?? '0.0000';
    }

    public function getEffectiveResponsiDikerjakanJamAttribute(): string
    {
        return $this->progress?->responsi_dikerjakan_jam ?? '0.0000';
    }

    public function getEffectiveTotalHutangJamAttribute(): string
    {
        return number_format((float) $this->effective_total_kompensasi_jam + (float) $this->effective_total_responsi_jam, 4, '.', '');
    }

    public function getEffectiveSisaHutangJamAttribute(): string
    {
        return number_format(max(0, (float) $this->effective_total_hutang_jam - (float) $this->effective_kompensasi_dikerjakan_jam - (float) $this->effective_responsi_dikerjakan_jam), 4, '.', '');
    }
}
