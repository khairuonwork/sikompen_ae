<?php

namespace App\Console\Commands;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Models\KompenResponHubActiveStudentImport;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubSystemSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('sikompen:purge-recycled-workbooks')]
#[Description('Permanently remove recyclable Sikompen source workbooks after their retention period.')]
class PurgeKompenResponHubRecycledWorkbooks extends Command
{
    public function handle(RecordKompenResponHubActivity $activity): int
    {
        $retentionDays = KompenResponHubSystemSetting::current()->workbook_recycle_bin_retention_days;
        $threshold = now()->subDays($retentionDays);
        $purgedWorkbookCount = $this->purgeWorkbookImports($threshold);
        $purgedRosterCount = $this->purgeActiveStudentImports($threshold);

        if ($purgedWorkbookCount + $purgedRosterCount > 0) {
            $activity->execute(
                'maintenance.recycled_workbooks_purged',
                'system_setting',
                'workbook_recycle_bin_retention',
                null,
                reason: "Masa simpan recycle bin {$retentionDays} hari diterapkan.",
                metadata: [
                    'retention_days' => $retentionDays,
                    'purged_workbook_count' => $purgedWorkbookCount,
                    'purged_roster_count' => $purgedRosterCount,
                ],
                subjectName: 'Recycle bin workbook',
            );
        }

        $this->components->info("{$purgedWorkbookCount} workbook dan {$purgedRosterCount} roster dihapus permanen dari recycle bin.");

        return self::SUCCESS;
    }

    private function purgeWorkbookImports(\DateTimeInterface $threshold): int
    {
        $purgedCount = 0;

        KompenResponHubImport::onlyTrashed()
            ->where('deleted_at', '<=', $threshold)
            ->chunkById(100, function ($imports) use (&$purgedCount): void {
                foreach ($imports as $import) {
                    if ($import->students()->exists()) {
                        continue;
                    }

                    if (str_starts_with($import->stored_path, 'kompen-respon-hub/imports/')) {
                        Storage::disk('local')->delete($import->stored_path);
                    }

                    $import->forceDelete();
                    $purgedCount++;
                }
            });

        return $purgedCount;
    }

    private function purgeActiveStudentImports(\DateTimeInterface $threshold): int
    {
        $purgedCount = 0;

        KompenResponHubActiveStudentImport::onlyTrashed()
            ->where('deleted_at', '<=', $threshold)
            ->chunkById(100, function ($imports) use (&$purgedCount): void {
                foreach ($imports as $import) {
                    if (str_starts_with($import->stored_path, 'kompen-respon-hub/imports/active-students/')) {
                        Storage::disk('local')->delete($import->stored_path);
                    }

                    $import->forceDelete();
                    $purgedCount++;
                }
            });

        return $purgedCount;
    }
}
