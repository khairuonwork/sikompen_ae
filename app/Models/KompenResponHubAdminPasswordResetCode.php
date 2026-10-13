<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubAdminPasswordResetCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $admin_id
 * @property int $recipient_id
 * @property string $code_hash
 * @property int $attempts
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $used_at
 */
class KompenResponHubAdminPasswordResetCode extends Model
{
    /** @use HasFactory<KompenResponHubAdminPasswordResetCodeFactory> */
    use HasFactory;

    protected $table = 'sikompen_admin_password_reset_codes';

    protected $fillable = ['admin_id', 'recipient_id', 'code_hash', 'attempts', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }
}
