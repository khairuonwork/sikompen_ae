<?php

namespace App\Models;

use Database\Factories\KompenResponHubAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class KompenResponHubAdmin extends Authenticatable
{
    /** @use HasFactory<KompenResponHubAdminFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }
}
