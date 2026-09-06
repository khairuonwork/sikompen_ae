<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KompenResponHubStudent extends Model
{
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
        return $this->belongsTo(KompenResponHubImport::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(KompenResponHubDetail::class);
    }
}
