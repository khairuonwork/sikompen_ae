<?php

namespace Database\Seeders;

use App\Models\KompenResponHubAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SikompenEmergencyAdminSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{username: string|null, password: string|null}> $accounts */
        $accounts = config('kompen-respon-hub.emergency_admins');

        foreach ($accounts as ['username' => $username, 'password' => $password]) {
            if (! is_string($username) || ! is_string($password) || $username === '' || $password === '') {
                throw new \RuntimeException('Konfigurasi dua akun darurat Sikompen belum lengkap.');
            }

            KompenResponHubAdmin::query()->updateOrCreate(
                ['username' => Str::lower($username)],
                [
                    'password' => Hash::make($password),
                    'is_emergency_admin' => true,
                ],
            );
        }
    }
}
