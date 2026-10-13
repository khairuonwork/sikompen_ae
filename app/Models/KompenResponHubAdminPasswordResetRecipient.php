<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubAdminPasswordResetRecipientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $email
 * @property int|null $added_by_admin_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class KompenResponHubAdminPasswordResetRecipient extends Model
{
    /** @use HasFactory<KompenResponHubAdminPasswordResetRecipientFactory> */
    use HasFactory;

    protected $table = 'sikompen_admin_password_reset_recipients';

    protected $fillable = ['email', 'added_by_admin_id'];

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }
}
