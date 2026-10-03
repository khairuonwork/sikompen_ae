<?php

namespace App\Models;

use Database\Factories\KompenResponHubDetailOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KompenResponHubDetailOverride extends Model
{
    /** @use HasFactory<KompenResponHubDetailOverrideFactory> */
    use HasFactory;

    protected $table = 'sikompen_detail_kompen_overrides';

    protected $fillable = ['source_key', 'override_values', 'reason', 'updated_by_admin_id'];

    protected function casts(): array
    {
        return ['override_values' => 'array'];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }
}
