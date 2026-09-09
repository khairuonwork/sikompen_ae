<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KompenResponHubDetail extends Model
{
    protected $table = 'sikompen_detail_kompen';

    protected $fillable = [
        'kompen_respon_hub_student_id',
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
}
