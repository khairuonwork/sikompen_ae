<?php

namespace App\Console\Commands;

use App\Models\KompenResponHubApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sikompen:revoke-api-client {clientId : Identifier client yang akan dicabut}')]
#[Description('Revoke an outbound Kompen Respon API client immediately.')]
class RevokeKompenResponHubApiClient extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $client = KompenResponHubApiClient::query()
            ->where('client_id', $this->argument('clientId'))
            ->first();

        if (! $client instanceof KompenResponHubApiClient) {
            $this->components->error('Client API tidak ditemukan.');

            return self::FAILURE;
        }

        $client->forceFill(['is_active' => false])->save();
        $this->components->info("Client {$client->client_id} telah dicabut.");

        return self::SUCCESS;
    }
}
