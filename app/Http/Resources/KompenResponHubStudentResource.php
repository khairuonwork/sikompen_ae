<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KompenResponHubStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tingkat' => $this->tingkat,
            'nim' => $this->nim,
            'nama_mahasiswa' => $this->nama_mahasiswa,
            'kelas' => $this->kelas,
            'periode_semester' => $this->periode_semester,
            'total_jam_terlambat' => $this->total_jam_terlambat,
            'total_jam_sakit' => $this->total_jam_sakit,
            'total_jam_izin' => $this->total_jam_izin,
            'total_jam_bolos' => $this->total_jam_bolos,
            'total_kompensasi_jam' => $this->total_kompensasi_jam,
            'total_responsi_jam' => $this->total_responsi_jam,
            'total_hutang_jam' => $this->total_hutang_jam,
            'kompensasi_dikerjakan_jam' => $this->kompensasi_dikerjakan_jam,
            'sisa_hutang_jam' => $this->sisa_hutang_jam,
        ];
    }
}
