<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubStudentAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $nim
 * @property string $password
 * @property CarbonInterface|null $password_changed_at
 * @property CarbonInterface|null $last_login_at
 * @property string|null $remember_token
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class KompenResponHubStudentAccount extends Authenticatable
{
    /** @use HasFactory<KompenResponHubStudentAccountFactory> */
    use HasFactory;

    protected $table = 'sikompen_student_accounts';

    protected $fillable = [
        'nim',
        'password',
        'password_changed_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    public function requiresPasswordChange(): bool
    {
        return $this->password_changed_at === null;
    }
}
