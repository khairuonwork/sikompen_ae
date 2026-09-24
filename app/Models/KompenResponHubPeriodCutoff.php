<?php

namespace App\Models;

use Database\Factories\KompenResponHubPeriodCutoffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KompenResponHubPeriodCutoff extends Model
{
    /** @use HasFactory<KompenResponHubPeriodCutoffFactory> */
    use HasFactory;

    protected $table = 'sikompen_periode_cutoffs';

    protected $fillable = ['periode_semester', 'deadline_at', 'timezone', 'updated_by_admin_id', 'closed_at', 'closed_by_admin_id'];

    protected function casts(): array
    {
        return ['deadline_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }
}
