<?php

namespace App\Http\Resources;

use App\Models\KompenResponHubImportTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KompenResponHubImportTask */
class KompenResponHubImportTaskResource extends JsonResource
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
            'status' => $this->status,
            'progress' => $this->progress,
            'progress_message' => $this->progress_message,
            'error_message' => $this->error_message,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
