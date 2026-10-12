<?php

namespace App\Http\Resources;

use App\Models\KompenResponHubActiveStudentImportSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KompenResponHubActiveStudentImportSource */
class KompenResponHubActiveStudentImportSourceResource extends JsonResource
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
            'periode_semester' => $this->periode_semester,
            'kelas' => $this->kelas,
            'activated_at' => $this->activated_at->toIso8601String(),
            'import' => [
                'id' => $this->importBatch->id,
                'original_filename' => $this->importBatch->original_filename,
                'periode_semester' => $this->importBatch->periode_semester,
                'student_count' => $this->importBatch->student_count,
                'imported_at' => $this->importBatch->imported_at->toIso8601String(),
            ],
        ];
    }
}
