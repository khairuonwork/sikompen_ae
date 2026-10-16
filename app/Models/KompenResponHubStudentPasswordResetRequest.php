<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubStudentPasswordResetRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nim
 * @property string $reset_code
 * @property int $attempts
 * @property CarbonInterface $requested_at
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $fulfilled_at
 */
class KompenResponHubStudentPasswordResetRequest extends Model
{
    /** @use HasFactory<KompenResponHubStudentPasswordResetRequestFactory> */
    use HasFactory;

    protected $table = 'sikompen_student_password_reset_requests';

    protected $fillable = ['nim', 'reset_code', 'attempts', 'requested_at', 'expires_at', 'fulfilled_at'];

    protected $hidden = ['reset_code'];

    protected function casts(): array
    {
        return [
            'reset_code' => 'encrypted',
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }
}
