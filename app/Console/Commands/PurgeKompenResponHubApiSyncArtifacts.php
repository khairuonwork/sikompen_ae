<?php

namespace App\Console\Commands;

use App\Models\KompenResponHubApiSyncRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('sikompen:purge-api-sync-artifacts')]
#[Description('Expire outbound API NDJSON.GZ artifacts after their retention window.')]
class PurgeKompenResponHubApiSyncArtifacts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredCount = 0;

        KompenResponHubApiSyncRun::query()
            ->where('status', 'ready')
            ->where('expires_at', '<=', now())
            ->chunkById(200, function ($runs) use (&$expiredCount): void {
                foreach ($runs as $run) {
                    if ($run->artifact_path !== null) {
                        Storage::disk('local')->delete($run->artifact_path);
                    }

                    $run->forceFill([
                        'status' => 'expired',
                        'artifact_path' => null,
                    ])->save();
                    $expiredCount++;
                }
            });

        $this->components->info("{$expiredCount} artifact API kedaluwarsa diproses.");

        return self::SUCCESS;
    }
}
