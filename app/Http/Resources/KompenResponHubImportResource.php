<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KompenResponHubImportResource extends JsonResource
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
            'uploader_name' => $this->uploader_name,
            'uploader_email' => $this->uploader_email,
            'periode_semester' => $this->periode_semester,
            'original_filename' => $this->original_filename,
            'class_count' => $this->class_count,
            'student_count' => $this->student_count,
            'detail_count' => $this->detail_count,
            'imported_at' => $this->imported_at?->toIso8601String(),
        ];
    }
}
