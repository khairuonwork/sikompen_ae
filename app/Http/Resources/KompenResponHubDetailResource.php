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
            'mata_kuliah' => $this->mata_kuliah,
            'nama_dosen' => $this->nama_dosen,
            'tanggal' => $this->tanggal?->format('Y-m-d'),
            'jenis_pertemuan' => $this->jenis_pertemuan,
            'presensi' => $this->presensi,
            'menit_keterlambatan' => $this->menit_keterlambatan,
            'keterangan' => $this->keterangan,
            'jam_kompensasi' => $this->jam_kompensasi,
            'jam_responsi' => $this->jam_responsi,
        ];
    }
}
