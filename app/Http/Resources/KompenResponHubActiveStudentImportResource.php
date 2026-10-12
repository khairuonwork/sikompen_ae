<?php

namespace App\Http\Resources;

use App\Models\KompenResponHubActiveStudentImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KompenResponHubActiveStudentImport */
class KompenResponHubActiveStudentImportResource extends JsonResource
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
            'original_filename' => $this->original_filename,
            'periode_semester' => $this->periode_semester,
            'student_count' => $this->student_count,
            'class_count' => $this->class_count,
            'uploader_name' => $this->uploader_name,
            'uploader_email' => $this->uploader_email,
            'imported_at' => $this->imported_at->toIso8601String(),
            'is_active' => $this->relationLoaded('activeReference') && $this->activeReference !== null,
            'activated_at' => $this->relationLoaded('activeReference')
                ? $this->activeReference?->activated_at?->toIso8601String()
                : null,
        ];
    }
}
