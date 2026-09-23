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
            'effective_total_kompensasi_jam' => $this->effective_total_kompensasi_jam,
            'effective_total_responsi_jam' => $this->effective_total_responsi_jam,
            'effective_total_hutang_jam' => $this->effective_total_hutang_jam,
            'effective_kompensasi_dikerjakan_jam' => $this->effective_kompensasi_dikerjakan_jam,
            'effective_responsi_dikerjakan_jam' => $this->effective_responsi_dikerjakan_jam,
            'effective_sisa_hutang_jam' => $this->effective_sisa_hutang_jam,
            'last_worked_at' => $this->progress?->last_worked_at?->toIso8601String(),
            'has_summary_override' => $this->summaryOverride !== null,
            'warning' => $this->latestWarning === null ? null : [
                'id' => $this->latestWarning->id,
                'classification' => $this->latestWarning->classification,
                'letter_status' => $this->latestWarning->letter_status,
                'resolution' => $this->latestWarning->resolution,
            ],
        ];
    }
}
