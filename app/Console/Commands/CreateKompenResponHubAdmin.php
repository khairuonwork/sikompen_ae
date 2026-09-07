<?php

namespace App\Console\Commands;

use App\Models\KompenResponHubAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateKompenResponHubAdmin extends Command
{
    protected $signature = 'kompen-respon-hub:create-admin
                            {email : Email address for the administrator}';

    protected $description = 'Create one administrator account for Kompen Respon Hub';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Email admin tidak valid.');

            return self::FAILURE;
        }

        if (KompenResponHubAdmin::query()->where('email', $email)->exists()) {
            $this->error('Email admin tersebut sudah terdaftar.');

            return self::FAILURE;
        }

        $password = $this->secret('Password admin (minimal 12 karakter)');
        $confirmation = $this->secret('Ulangi password admin');

        if (! is_string($password) || mb_strlen($password) < 12 || $password !== $confirmation) {
            $this->error('Password harus sama dan minimal 12 karakter.');

            return self::FAILURE;
        }

        KompenResponHubAdmin::create([
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("Admin {$email} berhasil dibuat.");

        return self::SUCCESS;
    }
}
