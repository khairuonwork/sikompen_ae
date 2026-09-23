<?php

namespace App\Http\Resources;

use App\Models\KompenResponHubWarningLetter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KompenResponHubWarningLetterResource extends JsonResource
{
    /** @mixin KompenResponHubWarningLetter */
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->current_student_id,
            'nim' => $this->nim,
            'nama_mahasiswa' => $this->nama_mahasiswa,
            'kelas' => $this->kelas,
            'periode_semester' => $this->periode_semester,
            'classification' => $this->classification,
            'letter_status' => $this->letter_status,
            'resolution' => $this->resolution,
            'snapshot' => $this->snapshot,
            'reason' => $this->reason,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
