<?php

namespace App\Console\Commands;

use App\Models\KompenResponHubApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('sikompen:create-api-client
    {clientId : Identifier unik client, misalnya si-admin-production}
    {name : Nama sistem peminta}
    {--ip=* : IP atau CIDR yang diizinkan, dapat diulang}
    {--rate=30 : Batas request per menit}')]
#[Description('Create an outbound Kompen Respon API client and display its token once.')]
class CreateKompenResponHubApiClient extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $clientId = Str::lower(trim((string) $this->argument('clientId')));
        $name = trim((string) $this->argument('name'));
        $allowedIps = array_values(array_filter($this->option('ip'), fn (mixed $ip): bool => is_string($ip) && $this->isValidIpRule($ip)));
        $rate = filter_var($this->option('rate'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 600],
        ]);

        if (! preg_match('/^[a-z0-9][a-z0-9-]{2,79}$/', $clientId) || $name === '' || $name !== Str::squish($name) || $allowedIps === [] || $rate === false) {
            $this->components->error('Client ID, nama, IP/CIDR, atau rate tidak valid. Pastikan minimal satu --ip valid diberikan.');

            return self::FAILURE;
        }

        if (KompenResponHubApiClient::query()->where('client_id', $clientId)->exists()) {
            $this->components->error('Client ID sudah digunakan. Gunakan identifier lain atau cabut client lama terlebih dahulu.');

            return self::FAILURE;
        }

        $token = Str::random(64);

        KompenResponHubApiClient::query()->create([
            'client_id' => $clientId,
            'name' => $name,
            'token_hash' => hash('sha256', $token),
            'scopes' => ['kompen-respon:read'],
            'allowed_ips' => $allowedIps,
            'rate_limit_per_minute' => $rate,
            'is_active' => true,
        ]);

        $this->components->warn('Simpan token berikut sekarang. Token mentah tidak dapat ditampilkan kembali.');
        $this->line("Client ID: {$clientId}");
        $this->line("Bearer token: {$token}");

        return self::SUCCESS;
    }

    private function isValidIpRule(string $rule): bool
    {
        [$ip, $prefix] = str_contains($rule, '/') ? explode('/', $rule, 2) : [$rule, null];

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($prefix === null) {
            return true;
        }

        $maxPrefix = str_contains($ip, ':') ? 128 : 32;

        return ctype_digit($prefix) && (int) $prefix >= 0 && (int) $prefix <= $maxPrefix;
    }
}
