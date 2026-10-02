<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\KompenResponHub\RestoreKompenResponHubImportVersion;
use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class KompenResponHubImportRollbackController extends Controller
{
    public function destroy(
        SiAdminProxyAccess $access,
        RecordKompenResponHubActivity $activity,
        RestoreKompenResponHubImportVersion $restoreImportVersion,
    ): RedirectResponse {
        $actor = $access->actor(request());

        $hasActiveImportTask = KompenResponHubImportTask::query()
            ->whereIn('status', [
                KompenResponHubImportTask::STATUS_QUEUED,
                KompenResponHubImportTask::STATUS_PROCESSING,
            ])
            ->exists();

        if ($hasActiveImportTask) {
            return to_route('admin.kompen-respon.index', ['tab' => 'imports'])
                ->with('error', 'Tunggu proses upload yang sedang berjalan selesai sebelum menjalankan rollback.');
        }

        $latestImport = KompenResponHubImport::query()
            ->whereHas('students')
            ->latest('imported_at')
            ->latest('id')
            ->first();

        if ($latestImport === null) {
            return to_route('admin.kompen-respon.index', ['tab' => 'imports'])
                ->with('error', 'Belum ada versi impor aktif yang dapat di-rollback.');
        }

        try {
            $rollback = $restoreImportVersion->execute(
                $latestImport,
                $actor['email'],
                $actor['email'],
            );
        } catch (\LogicException $exception) {
            return to_route('admin.kompen-respon.index', ['tab' => 'imports'])
                ->with('error', $exception->getMessage());
        }

        Cache::forever(
            KompenResponHubDataQuery::FILTER_OPTIONS_CACHE_VERSION_KEY,
            (int) Cache::get(KompenResponHubDataQuery::FILTER_OPTIONS_CACHE_VERSION_KEY, 1) + 1,
        );
        $admin = is_int($actor['id'])
            ? KompenResponHubAdmin::query()->find($actor['id'])
            : null;
        $restoredFilenames = collect($rollback['restored_imports'])
            ->pluck('original_filename')
            ->implode(', ');

        $activity->execute(
            'import.rolled_back',
            'import',
            (string) $rollback['rolled_back_import_id'],
            $admin,
            request(),
            null,
            $rollback['periode_semester'],
            null,
            $rollback['restored']
                ? 'Versi impor aktif dikembalikan ke workbook sebelumnya.'
                : 'Impor awal dibatalkan karena belum ada versi sebelumnya.',
            afterState: [
                'restored' => $rollback['restored'],
                'restored_imports' => $rollback['restored_imports'],
            ],
            metadata: [
                'restored_import_filenames' => $restoredFilenames,
            ],
        );

        return to_route('admin.kompen-respon.index', ['tab' => 'imports'])
            ->with(
                'success',
                $rollback['restored']
                    ? "Rollback selesai. Data aktif dikembalikan ke versi {$restoredFilenames}."
                    : 'Rollback selesai. Impor awal dibatalkan; file dan riwayat upload tetap tersimpan.',
            );
    }
}
