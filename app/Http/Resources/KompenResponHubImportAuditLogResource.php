<?php

namespace App\Http\Resources;

use App\Models\KompenResponHubImportAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KompenResponHubImportAuditLog */
class KompenResponHubImportAuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isActiveStudentImport = $this->import_type === KompenResponHubImportAuditLog::ImportTypeActiveStudents;
        $versionStatus = match (true) {
            $isActiveStudentImport && $this->sourceActiveStudentImport !== null => 'stored',
            $isActiveStudentImport => 'unavailable',
            $this->sourceImport === null => 'unavailable',
            $this->sourceImport->activeReference !== null => 'active',
            default => 'stored',
        };

        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'import_type' => $this->import_type,
            'source_import_id' => $this->source_import_id,
            'source_active_student_import_id' => $this->source_active_student_import_id,
            'can_download_file' => $isActiveStudentImport
                ? $this->relationLoaded('sourceActiveStudentImport') && $this->sourceActiveStudentImport !== null
                : $this->relationLoaded('sourceImport') && $this->sourceImport !== null,
            'version_status' => $versionStatus,
            'version_activated_at' => $this->sourceImport?->activeReference?->activated_at?->toIso8601String(),
            'version_imported_at' => $this->sourceImport?->imported_at?->toIso8601String(),
            'actor_name' => $this->actor_name,
            'actor_username' => $this->actor_username,
            'periode_semester' => $this->periode_semester,
            'original_filename' => $this->original_filename,
            'class_count' => $this->class_count,
            'student_count' => $this->student_count,
            'detail_count' => $this->detail_count,
            'metadata' => $this->metadata,
            'quality_report' => $this->when(
                $this->relationLoaded('sourceImport') && $this->sourceImport !== null,
                $this->sourceImport?->quality_report,
            ),
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
