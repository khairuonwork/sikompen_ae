<?php

namespace App\Console\Commands;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Models\KompenResponHubExportTask;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('sikompen:purge-exports')]
#[Description('Remove expired Sikompen export files and task records.')]
class PurgeKompenResponHubExports extends Command
{
    public function handle(RecordKompenResponHubActivity $activity): int
    {
        $deletedCount = 0;

        KompenResponHubExportTask::query()
            ->where('expires_at', '<=', now())
            ->chunkById(200, function ($tasks) use (&$deletedCount): void {
                foreach ($tasks as $task) {
                    if ($task->output_path !== null) {
                        Storage::disk('local')->delete($task->output_path);
                    }

                    $task->delete();
                    $deletedCount++;
                }
            });

        if ($deletedCount > 0) {
            $activity->execute(
                'maintenance.exports_purged',
                'export',
                null,
                null,
                reason: 'File dan task ekspor kedaluwarsa dihapus.',
                metadata: ['deleted_count' => $deletedCount],
            );
        }

        $this->components->info("{$deletedCount} ekspor kedaluwarsa dihapus.");

        return self::SUCCESS;
    }
}
