<?php

namespace App\Console\Commands;

use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubSystemSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sikompen:purge-import-audit-logs')]
#[Description('Remove Sikompen upload audit logs older than the configured retention period.')]
class PurgeKompenResponHubImportAuditLogs extends Command
{
    public function handle(): int
    {
        $retentionDays = KompenResponHubSystemSetting::current()->import_audit_log_retention_days;
        $deletedCount = KompenResponHubImportAuditLog::query()
            ->where('occurred_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->components->info("{$deletedCount} Log Upload yang melewati retensi dihapus.");

        return self::SUCCESS;
    }
}
