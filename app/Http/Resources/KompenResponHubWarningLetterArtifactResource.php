<?php

namespace App\Http\Resources;

use App\Models\KompenResponHubWarningLetterArtifact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KompenResponHubWarningLetterArtifact */
class KompenResponHubWarningLetterArtifactResource extends JsonResource
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
            'cutoff_id' => $this->cutoff_id,
            'periode_semester' => $this->cutoff?->periode_semester,
            'status' => $this->status,
            'revision' => $this->revision,
            'display_filename' => $this->display_filename,
            'row_count' => $this->row_count,
            'source_provenance' => $this->source_provenance,
            'finalized_at' => $this->finalized_at->toIso8601String(),
            'finalized_by_admin_id' => $this->finalized_by_admin_id,
            'correction_started_at' => $this->correction_started_at?->toIso8601String(),
        ];
    }
}
