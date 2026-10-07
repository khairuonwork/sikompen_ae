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
 * @property int $kompen_respon_hub_import_id
 * @property string $nim
 * @property string $periode_semester
 * @property string $nama_mahasiswa
 * @property string $kelas
 * @property int $tingkat
 * @property string $total_jam_terlambat
 * @property string $total_jam_sakit
 * @property string $total_jam_izin
 * @property string $total_jam_bolos
 * @property string $total_kompensasi_jam
 * @property string $total_responsi_jam
 * @property string $total_hutang_jam
 * @property string $kompensasi_dikerjakan_jam
 * @property string $sisa_hutang_jam
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read KompenResponHubImport|null $importBatch
 * @property-read Collection<int, KompenResponHubDetail> $details
 * @property-read KompenResponHubStudentProgress|null $progress
 * @property-read KompenResponHubStudentSummaryOverride|null $summaryOverride
 * @property-read KompenResponHubWarningLetter|null $latestWarning
 * @property-read KompenResponHubPeriodCutoff|null $cutoff
 * @property-read string $effective_total_kompensasi_jam
 * @property-read string $effective_total_responsi_jam
 * @property-read string $effective_kompensasi_dikerjakan_jam
 * @property-read string $effective_responsi_dikerjakan_jam
 * @property-read string $effective_total_hutang_jam
 * @property-read string $effective_sisa_hutang_jam
 */
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

    /** @return BelongsTo<KompenResponHubImport, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubImport::class, 'kompen_respon_hub_import_id');
    }

    /** @return HasMany<KompenResponHubDetail, $this> */
    public function details(): HasMany
    {
        return $this->hasMany(KompenResponHubDetail::class);
    }

    /** @return HasOne<KompenResponHubStudentProgress, $this> */
    public function progress(): HasOne
    {
        return $this->hasOne(KompenResponHubStudentProgress::class, 'current_student_id');
    }

    /** @return HasOne<KompenResponHubStudentSummaryOverride, $this> */
    public function summaryOverride(): HasOne
    {
        return $this->hasOne(KompenResponHubStudentSummaryOverride::class, 'current_student_id');
    }

    /** @return HasOne<KompenResponHubWarningLetter, $this> */
    public function latestWarning(): HasOne
    {
        return $this->hasOne(KompenResponHubWarningLetter::class, 'current_student_id')->latestOfMany();
    }

    /** @return HasOne<KompenResponHubPeriodCutoff, $this> */
    public function cutoff(): HasOne
    {
        return $this->hasOne(KompenResponHubPeriodCutoff::class, 'periode_semester', 'periode_semester');
    }

    public function getEffectiveTotalKompensasiJamAttribute(): string
    {
        $summaryOverride = $this->summaryOverrideRecord();

        if ($summaryOverride === null) {
            return $this->total_kompensasi_jam;
        }

        return $summaryOverride->total_kompensasi_jam;
    }

    public function getEffectiveTotalResponsiJamAttribute(): string
    {
        $summaryOverride = $this->summaryOverrideRecord();

        if ($summaryOverride === null) {
            return $this->total_responsi_jam;
        }

        return $summaryOverride->total_responsi_jam;
    }

    public function getEffectiveKompensasiDikerjakanJamAttribute(): string
    {
        $progress = $this->progressRecord();

        if ($progress === null) {
            return '0.0000';
        }

        return $progress->kompensasi_dikerjakan_jam;
    }

    public function getEffectiveResponsiDikerjakanJamAttribute(): string
    {
        $progress = $this->progressRecord();

        if ($progress === null) {
            return '0.0000';
        }

        return $progress->responsi_dikerjakan_jam;
    }

    public function getEffectiveTotalHutangJamAttribute(): string
    {
        return number_format((float) $this->effective_total_kompensasi_jam + (float) $this->effective_total_responsi_jam, 4, '.', '');
    }

    public function getEffectiveSisaHutangJamAttribute(): string
    {
        return number_format(max(0, (float) $this->effective_total_hutang_jam - (float) $this->effective_kompensasi_dikerjakan_jam - (float) $this->effective_responsi_dikerjakan_jam), 4, '.', '');
    }

    private function progressRecord(): ?KompenResponHubStudentProgress
    {
        $progress = $this->getRelationValue('progress');

        return $progress instanceof KompenResponHubStudentProgress ? $progress : null;
    }

    private function summaryOverrideRecord(): ?KompenResponHubStudentSummaryOverride
    {
        $summaryOverride = $this->getRelationValue('summaryOverride');

        return $summaryOverride instanceof KompenResponHubStudentSummaryOverride ? $summaryOverride : null;
    }
}
