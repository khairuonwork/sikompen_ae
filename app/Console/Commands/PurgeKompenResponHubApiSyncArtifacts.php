<?php

namespace App\Console\Commands;

use App\Models\KompenResponHubApiSyncRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('sikompen:purge-api-sync-artifacts')]
#[Description('Expire outbound API artifacts and purge old terminal sync run records.')]
class PurgeKompenResponHubApiSyncArtifacts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredCount = 0;
        $purgedRunCount = 0;

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

        $terminalRunThreshold = now()->subDays(max(1, (int) config('kompen-respon-hub.api_sync.run_retention_days', 7)));

        KompenResponHubApiSyncRun::query()
            ->whereIn('status', ['expired', 'failed'])
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $terminalRunThreshold)
            ->chunkById(200, function ($runs) use (&$purgedRunCount): void {
                foreach ($runs as $run) {
                    if ($run->artifact_path !== null) {
                        Storage::disk('local')->delete($run->artifact_path);
                    }

                    $run->delete();
                    $purgedRunCount++;
                }
            });

        $this->components->info("{$expiredCount} artifact API kedaluwarsa dan {$purgedRunCount} riwayat terminal diproses.");

        return self::SUCCESS;
    }
}
