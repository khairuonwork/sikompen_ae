<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubActivityLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_type
 * @property string $subject_type
 * @property string|null $subject_reference
 * @property string|null $nim
 * @property string|null $subject_name
 * @property string|null $periode_semester
 * @property string|null $kelas
 * @property string $actor_type
 * @property int|null $actor_admin_id
 * @property string|null $actor_name
 * @property string|null $actor_email
 * @property string|null $reason
 * @property array<string, mixed>|null $before_state
 * @property array<string, mixed>|null $after_state
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonInterface $occurred_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class KompenResponHubActivityLog extends Model
{
    /** @use HasFactory<KompenResponHubActivityLogFactory> */
    use HasFactory;

    protected $table = 'sikompen_activity_logs';

    protected $fillable = ['event_type', 'subject_type', 'subject_reference', 'nim', 'subject_name', 'periode_semester', 'kelas', 'actor_type', 'actor_admin_id', 'actor_name', 'actor_email', 'reason', 'before_state', 'after_state', 'metadata', 'ip_address', 'user_agent', 'occurred_at'];

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array', 'metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }
}
