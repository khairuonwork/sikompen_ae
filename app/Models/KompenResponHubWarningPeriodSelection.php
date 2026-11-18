<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubWarningPeriodSelectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $selection_key
 * @property int $sikompen_period_cutoff_id
 * @property int|null $selected_by_admin_id
 * @property CarbonInterface $selected_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read KompenResponHubPeriodCutoff $cutoff
 * @property-read KompenResponHubAdmin|null $selectedByAdmin
 */
class KompenResponHubWarningPeriodSelection extends Model
{
    public const ManagedWarningPeriodSelectionKey = 'warning-period';

    /** @use HasFactory<KompenResponHubWarningPeriodSelectionFactory> */
    use HasFactory;

    protected $table = 'sikompen_warning_period_selections';

    protected $fillable = [
        'selection_key',
        'sikompen_period_cutoff_id',
        'selected_by_admin_id',
        'selected_at',
    ];

    protected function casts(): array
    {
        return [
            'sikompen_period_cutoff_id' => 'integer',
            'selected_by_admin_id' => 'integer',
            'selected_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return BelongsTo<KompenResponHubPeriodCutoff, $this> */
    public function cutoff(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubPeriodCutoff::class, 'sikompen_period_cutoff_id');
    }

    /** @return BelongsTo<KompenResponHubAdmin, $this> */
    public function selectedByAdmin(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubAdmin::class, 'selected_by_admin_id');
    }
}
