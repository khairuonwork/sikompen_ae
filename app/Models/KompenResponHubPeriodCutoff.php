<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubPeriodCutoffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $periode_semester
 * @property CarbonImmutable $deadline_at
 * @property string $timezone
 * @property int|null $updated_by_admin_id
 * @property CarbonImmutable|null $closed_at
 * @property int|null $closed_by_admin_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
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
