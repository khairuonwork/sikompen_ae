<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $kompen_respon_hub_student_id
 * @property string|null $source_key
 * @property CarbonInterface|null $tanggal
 * @property string $mata_kuliah
 * @property string $nama_dosen
 * @property string $jenis_pertemuan
 * @property string $presensi
 * @property int $menit_keterlambatan
 * @property string|null $keterangan
 * @property string $jam_kompensasi
 * @property string $jam_responsi
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read KompenResponHubStudent|null $student
 * @property-read KompenResponHubDetailOverride|null $override
 * @property-read string $effective_mata_kuliah
 * @property-read string $effective_nama_dosen
 * @property-read string $effective_tanggal
 * @property-read string $effective_jenis_pertemuan
 * @property-read string $effective_presensi
 * @property-read int $effective_menit_keterlambatan
 * @property-read string|null $effective_keterangan
 * @property-read string $effective_jam_kompensasi
 * @property-read string $effective_jam_responsi
 */
class KompenResponHubDetail extends Model
{
    protected $table = 'sikompen_detail_kompen';

    protected $fillable = [
        'kompen_respon_hub_student_id',
        'source_key',
        'tanggal',
        'mata_kuliah',
        'nama_dosen',
        'jenis_pertemuan',
        'presensi',
        'menit_keterlambatan',
        'keterangan',
        'jam_kompensasi',
        'jam_responsi',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'menit_keterlambatan' => 'integer',
            'jam_kompensasi' => 'decimal:4',
            'jam_responsi' => 'decimal:4',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return BelongsTo<KompenResponHubStudent, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(
            KompenResponHubStudent::class,
            'kompen_respon_hub_student_id',
        );
    }

    /** @return HasOne<KompenResponHubDetailOverride, $this> */
    public function override(): HasOne
    {
        return $this->hasOne(KompenResponHubDetailOverride::class, 'source_key', 'source_key');
    }

    public function getEffectiveMataKuliahAttribute(): string
    {
        return $this->override?->override_values['mata_kuliah'] ?? $this->mata_kuliah;
    }

    public function getEffectiveNamaDosenAttribute(): string
    {
        return $this->override?->override_values['nama_dosen'] ?? $this->nama_dosen;
    }

    public function getEffectiveTanggalAttribute(): string
    {
        return $this->override?->override_values['tanggal'] ?? $this->tanggal?->format('Y-m-d') ?? '';
    }

    public function getEffectiveJenisPertemuanAttribute(): string
    {
        return $this->override?->override_values['jenis_pertemuan'] ?? $this->jenis_pertemuan;
    }

    public function getEffectivePresensiAttribute(): string
    {
        return $this->override?->override_values['presensi'] ?? $this->presensi;
    }

    public function getEffectiveMenitKeterlambatanAttribute(): int
    {
        return (int) ($this->override?->override_values['menit_keterlambatan'] ?? $this->menit_keterlambatan);
    }

    public function getEffectiveKeteranganAttribute(): ?string
    {
        return $this->override?->override_values['keterangan'] ?? $this->keterangan;
    }

    public function getEffectiveJamKompensasiAttribute(): string
    {
        return (string) ($this->override?->override_values['jam_kompensasi'] ?? $this->jam_kompensasi);
    }

    public function getEffectiveJamResponsiAttribute(): string
    {
        return (string) ($this->override?->override_values['jam_responsi'] ?? $this->jam_responsi);
    }
}
