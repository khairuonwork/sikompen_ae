<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            KompenResponHubStudent::class,
            'kompen_respon_hub_student_id',
        );
    }

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
