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
    private const DeleteBatchSize = 500;

    public function handle(): int
    {
        $retentionDays = KompenResponHubSystemSetting::current()->import_audit_log_retention_days;
        $deletedCount = 0;
        $threshold = now('Asia/Jakarta')->subDays($retentionDays);

        KompenResponHubImportAuditLog::query()
            ->where('occurred_at', '<', $threshold)
            ->select('id')
            ->chunkById(self::DeleteBatchSize, function ($logs) use (&$deletedCount): void {
                $deletedCount += KompenResponHubImportAuditLog::query()
                    ->whereKey($logs->modelKeys())
                    ->delete();
            });

        $this->components->info("{$deletedCount} Log Upload yang melewati retensi dihapus.");

        return self::SUCCESS;
    }
}
