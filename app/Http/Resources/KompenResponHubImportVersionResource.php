<?php

namespace App\Http\Resources;

use App\Models\KompenResponHubImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KompenResponHubImport */
class KompenResponHubImportVersionResource extends JsonResource
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
            'original_filename' => $this->original_filename,
            'class_count' => $this->class_count,
            'student_count' => $this->student_count,
            'detail_count' => $this->detail_count,
            'uploaded_by_name' => $this->uploader_name,
            'uploaded_by_email' => $this->uploader_email,
            'imported_at' => $this->imported_at?->toIso8601String(),
            'is_active' => $this->activeReference !== null,
            'activated_at' => $this->activeReference?->activated_at?->toIso8601String(),
            'activated_by_email' => $this->activeReference?->activatedByAdmin?->email,
            'quality_report' => $this->quality_report,
        ];
    }
}
