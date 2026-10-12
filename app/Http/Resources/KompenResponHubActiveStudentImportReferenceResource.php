<?php

namespace App\Http\Resources;

use App\Models\KompenResponHubActiveStudentImportReference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KompenResponHubActiveStudentImportReference */
class KompenResponHubActiveStudentImportReferenceResource extends JsonResource
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
            'activated_at' => $this->activated_at->toIso8601String(),
            'import' => [
                'id' => $this->importBatch->id,
                'original_filename' => $this->importBatch->original_filename,
                'student_count' => $this->importBatch->student_count,
                'class_count' => $this->importBatch->class_count,
                'imported_at' => $this->importBatch->imported_at->toIso8601String(),
            ],
        ];
    }
}
