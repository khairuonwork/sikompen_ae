<?php

namespace App\Http\Resources;

use App\Models\KompenResponHubActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KompenResponHubActivityLogResource extends JsonResource
{
    /** @mixin KompenResponHubActivityLog */
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'subject_type' => $this->subject_type,
            'subject_reference' => $this->subject_reference,
            'nim' => $this->nim,
            'subject_name' => $this->subject_name,
            'periode_semester' => $this->periode_semester,
            'kelas' => $this->kelas,
            'actor_type' => $this->actor_type,
            'actor_name' => $this->actor_name,
            'actor_email' => $this->actor_email,
            'reason' => $this->reason,
            'before_state' => $this->before_state,
            'after_state' => $this->after_state,
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
