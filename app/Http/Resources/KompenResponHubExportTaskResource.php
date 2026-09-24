<?php

namespace App\Http\Resources;

use App\Models\KompenResponHubExportTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KompenResponHubExportTask */
class KompenResponHubExportTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'access_token' => $this->access_token,
            'resource' => $this->resource,
            'format' => $this->format,
            'status' => $this->status,
            'progress' => $this->progress,
            'progress_message' => $this->progress_message,
            'download_filename' => $this->download_filename,
            'error_message' => $this->error_message,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
