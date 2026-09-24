<?php

namespace App\Models;

use Database\Factories\KompenResponHubActivityLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
