<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $email
 * @property string $password
 * @property int $session_version
 * @property bool $is_emergency_admin
 * @property string|null $remember_token
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class KompenResponHubAdmin extends Authenticatable
{
    /** @use HasFactory<KompenResponHubAdminFactory> */
    use HasFactory;

    protected $table = 'sikompen_admins';

    protected $fillable = [
        'email',
        'password',
        'session_version',
        'is_emergency_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_emergency_admin' => 'boolean',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }
}
