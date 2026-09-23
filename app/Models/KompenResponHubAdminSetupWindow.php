<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\KompenResponHubAdminSetupWindowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $activation_code_hash
 * @property CarbonImmutable|null $expires_at
 */
class KompenResponHubAdminSetupWindow extends Model
{
    /** @use HasFactory<KompenResponHubAdminSetupWindowFactory> */
    use HasFactory;

    protected $table = 'sikompen_admin_setup_windows';

    protected $fillable = [
        'activation_code_hash',
        'expires_at',
        'opened_by_admin_id',
    ];

    protected $hidden = [
        'activation_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    public function isOpen(): bool
    {
        return $this->activation_code_hash !== null
            && $this->expires_at?->isFuture();
    }
}
