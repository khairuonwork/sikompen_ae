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
        $activeStudentCount = $this->sourceImport?->students_count ?? 0;
        $versionStatus = match (true) {
            $this->sourceImport === null => 'unavailable',
            $activeStudentCount === 0 => 'archived',
            $activeStudentCount < $this->sourceImport->student_count => 'partially_active',
            default => 'active',
        };

        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'source_import_id' => $this->source_import_id,
            'can_download_file' => $this->relationLoaded('sourceImport')
                && $this->sourceImport !== null,
            'can_restore_version' => $this->event_type === KompenResponHubImportAuditLog::EVENT_UPLOAD
                && $this->sourceImport !== null
                && $versionStatus !== 'active',
            'version_status' => $versionStatus,
            'actor_name' => $this->actor_name,
            'actor_email' => $this->actor_email,
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
