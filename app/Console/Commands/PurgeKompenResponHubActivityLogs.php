<?php

namespace App\Console\Commands;

use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubSystemSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sikompen:purge-activity-logs')]
#[Description('Remove Sikompen activity logs older than the configured retention period.')]
class PurgeKompenResponHubActivityLogs extends Command
{
    public function handle(): int
    {
        $retentionDays = KompenResponHubSystemSetting::current()->activity_log_retention_days;
        $deletedCount = KompenResponHubActivityLog::query()
            ->where('occurred_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->components->info("{$deletedCount} riwayat aktivitas yang melewati retensi dihapus.");

        return self::SUCCESS;
    }
}
