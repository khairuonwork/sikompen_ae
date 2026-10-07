<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\ActivateKompenResponHubImportVersion;
use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use App\Http\Requests\ActivateKompenResponHubImportVersionRequest;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class KompenResponHubImportActivationController extends Controller
{
    public function store(
        ActivateKompenResponHubImportVersionRequest $request,
        SiAdminProxyAccess $access,
        RecordKompenResponHubActivity $activity,
        ActivateKompenResponHubImportVersion $activateImportVersion,
        KompenResponHubImport $import,
    ): RedirectResponse {
        if ($this->hasActiveImportTask()) {
            return to_route('admin.kompen-respon.index', ['tab' => 'files'])
                ->with('error', 'Tunggu proses upload yang sedang berjalan selesai sebelum memilih versi data aktif.');
        }

        $actor = $access->actor($request);
        $admin = is_int($actor['id'])
            ? KompenResponHubAdmin::query()->find($actor['id'])
            : null;

        try {
            $activatedVersion = $activateImportVersion->execute(
                $import,
                $admin instanceof KompenResponHubAdmin ? $admin->email : $actor['email'],
                $actor['email'],
                $admin?->id,
            );
        } catch (\LogicException $exception) {
            return to_route('admin.kompen-respon.index', ['tab' => 'files'])
                ->with('error', $exception->getMessage());
        }

        Cache::forever(
            KompenResponHubDataQuery::FILTER_OPTIONS_CACHE_VERSION_KEY,
            (int) Cache::get(KompenResponHubDataQuery::FILTER_OPTIONS_CACHE_VERSION_KEY, 1) + 1,
        );

        $activity->execute(
            'import.version_activated',
            'import',
            (string) $activatedVersion['import_id'],
            $admin,
            $request,
            period: $activatedVersion['periode_semester'],
            reason: 'Versi workbook dipilih sebagai sumber data aktif untuk periode ini.',
            afterState: [
                'original_filename' => $activatedVersion['original_filename'],
                'classes' => $activatedVersion['classes'],
            ],
            metadata: [
                'previous_import' => $activatedVersion['previous_import'],
            ],
        );

        return to_route('admin.kompen-respon.index', ['tab' => 'files'])
            ->with('success', "Versi {$activatedVersion['original_filename']} kini menjadi sumber data aktif untuk periode {$activatedVersion['periode_semester']}.");
    }

    private function hasActiveImportTask(): bool
    {
        return KompenResponHubImportTask::query()
            ->whereIn('status', [
                KompenResponHubImportTask::STATUS_QUEUED,
                KompenResponHubImportTask::STATUS_PROCESSING,
            ])
            ->exists();
    }
}
