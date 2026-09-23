<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubAdmin;
use Illuminate\Http\Request;

class RecordKompenResponHubActivity
{
    /**
     * @param  array<string, mixed>|null  $beforeState
     * @param  array<string, mixed>|null  $afterState
     * @param  array<string, mixed>|null  $metadata
     */
    public function execute(
        string $eventType,
        string $subjectType,
        ?string $subjectReference,
        ?KompenResponHubAdmin $actor,
        ?Request $request = null,
        ?string $nim = null,
        ?string $period = null,
        ?string $class = null,
        ?string $reason = null,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?array $metadata = null,
    ): void {
        KompenResponHubActivityLog::create([
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_reference' => $subjectReference,
            'nim' => $nim,
            'periode_semester' => $period,
            'kelas' => $class,
            'actor_type' => $actor === null ? 'system' : 'admin',
            'actor_admin_id' => $actor?->id,
            'actor_name' => $actor?->email,
            'actor_email' => $actor?->email,
            'reason' => $reason,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'occurred_at' => now(),
        ]);
    }
}
