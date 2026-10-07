<?php

namespace App\Http\Resources;

use App\Models\KompenResponHubStudent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KompenResponHubStudent */
class KompenResponHubStudentSearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nim' => $this->nim,
            'nama_mahasiswa' => $this->nama_mahasiswa,
            'kelas' => $this->kelas,
            'tingkat' => $this->tingkat,
            'periode_semester' => $this->periode_semester,
        ];
    }
}
