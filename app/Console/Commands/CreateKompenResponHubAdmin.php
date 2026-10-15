<?php

namespace App\Console\Commands;

use App\Models\KompenResponHubAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateKompenResponHubAdmin extends Command
{
    protected $signature = 'kompen-respon-hub:create-admin
                            {username : Username untuk administrator}';

    protected $description = 'Create one administrator account for Kompen Respon Hub';

    public function handle(): int
    {
        $username = Str::lower(trim((string) $this->argument('username')));

        if (preg_match('/^[a-z0-9][a-z0-9._@-]{2,63}$/', $username) !== 1) {
            $this->error('Username admin tidak valid.');

            return self::FAILURE;
        }

        if (KompenResponHubAdmin::query()->where('username', $username)->exists()) {
            $this->error('Username admin tersebut sudah terdaftar.');

            return self::FAILURE;
        }

        $password = $this->secret('Password admin (minimal 12 karakter)');
        $confirmation = $this->secret('Ulangi password admin');

        if (! is_string($password) || mb_strlen($password) < 12 || $password !== $confirmation) {
            $this->error('Password harus sama dan minimal 12 karakter.');

            return self::FAILURE;
        }

        KompenResponHubAdmin::create([
            'username' => $username,
            'password' => Hash::make($password),
        ]);

        $this->info("Admin {$username} berhasil dibuat.");

        return self::SUCCESS;
    }
}
