<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubDetailOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $source_key
 * @property array<string, mixed> $override_values
 * @property string $reason
 * @property int|null $updated_by_admin_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
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
