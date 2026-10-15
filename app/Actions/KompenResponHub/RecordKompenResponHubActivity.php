<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RecordKompenResponHubActivity
{
    private const MaxReasonLength = 2_000;

    private const MaxStateStringLength = 1_000;

    private const MaxStateEntries = 30;

    private const MaxStateDepth = 3;

    /** @var list<string> */
    private const SensitiveStateKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'otp',
        'token',
        'access_token',
        'reset_code',
        'code',
        'code_hash',
        'secret',
    ];

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
        ?string $subjectName = null,
    ): bool {
        try {
            KompenResponHubActivityLog::create([
                'event_type' => $this->limit($eventType, 80),
                'subject_type' => $this->limit($subjectType, 80),
                'subject_reference' => $this->limit($subjectReference, 100),
                'nim' => $this->limit($nim, 20),
                'subject_name' => $this->limit($subjectName, 100),
                'periode_semester' => $this->limit($period, 50),
                'kelas' => $this->limit($class, 20),
                'actor_type' => $actor === null ? 'system' : 'admin',
                'actor_admin_id' => $actor?->id,
                'actor_name' => $this->limit($actor?->username, 100),
                'actor_username' => $this->limit($actor?->username, 100),
                'reason' => $this->limit($reason, self::MaxReasonLength),
                'before_state' => $this->sanitizeState($beforeState),
                'after_state' => $this->sanitizeState($afterState),
                'metadata' => $this->sanitizeState($metadata),
                'ip_address' => $request?->ip(),
                'user_agent' => $this->limit($request?->userAgent(), 512),
                'occurred_at' => now('Asia/Jakarta'),
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Sikompen activity log could not be persisted.', [
                'event_type' => $this->limit($eventType, 80),
                'subject_type' => $this->limit($subjectType, 80),
                'subject_reference' => $this->limit($subjectReference, 100),
                'exception' => $exception,
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>|null  $state
     * @return array<string, mixed>|null
     */
    private function sanitizeState(?array $state): ?array
    {
        if ($state === null) {
            return null;
        }

        return $this->sanitizeArray($state);
    }

    /**
     * @param  array<array-key, mixed>  $state
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $state, int $depth = 0): array
    {
        if ($depth >= self::MaxStateDepth) {
            return ['_truncated' => true];
        }

        $sanitized = [];

        foreach (array_slice($state, 0, self::MaxStateEntries, true) as $key => $value) {
            $stringKey = (string) $key;

            if (in_array(Str::lower($stringKey), self::SensitiveStateKeys, true)) {
                $sanitized[$stringKey] = '[disamarkan]';

                continue;
            }

            $sanitized[$stringKey] = match (true) {
                is_array($value) => $this->sanitizeArray($value, $depth + 1),
                is_string($value) => $this->limit($value, self::MaxStateStringLength),
                is_scalar($value), $value === null => $value,
                default => '[nilai tidak didukung]',
            };
        }

        if (count($state) > self::MaxStateEntries) {
            $sanitized['_truncated'] = true;
        }

        return $sanitized;
    }

    private function limit(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        return Str::limit($value, $length, '');
    }
}
