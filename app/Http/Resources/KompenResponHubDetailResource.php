<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KompenResponHubDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->kompen_respon_hub_student_id,
            'tingkat' => $this->student?->tingkat,
            'nim' => $this->student?->nim,
            'nama_mahasiswa' => $this->student?->nama_mahasiswa,
            'kelas' => $this->student?->kelas,
            'periode_semester' => $this->student?->periode_semester,
            'mata_kuliah' => $this->effective_mata_kuliah,
            'nama_dosen' => $this->effective_nama_dosen,
            'tanggal' => $this->effective_tanggal,
            'jenis_pertemuan' => $this->effective_jenis_pertemuan,
            'presensi' => $this->effective_presensi,
            'menit_keterlambatan' => $this->effective_menit_keterlambatan,
            'keterangan' => $this->effective_keterangan,
            'jam_kompensasi' => $this->effective_jam_kompensasi,
            'jam_responsi' => $this->effective_jam_responsi,
            'has_override' => $this->override !== null,
        ];
    }
}
