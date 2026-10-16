<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubAdminPasswordResetCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $admin_id
 * @property int|null $recipient_id
 * @property int|null $generated_by_admin_id
 * @property string|null $code_hash
 * @property int $attempts
 * @property CarbonInterface|null $requested_at
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $used_at
 */
class KompenResponHubAdminPasswordResetCode extends Model
{
    /** @use HasFactory<KompenResponHubAdminPasswordResetCodeFactory> */
    use HasFactory;

    protected $table = 'sikompen_admin_password_reset_codes';

    protected $fillable = ['admin_id', 'recipient_id', 'generated_by_admin_id', 'code_hash', 'attempts', 'requested_at', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return BelongsTo<KompenResponHubAdmin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubAdmin::class, 'admin_id');
    }
}
