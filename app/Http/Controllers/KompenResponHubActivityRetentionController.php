<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use App\Http\Requests\UpdateKompenResponHubActivityRetentionRequest;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubSystemSetting;
use Illuminate\Http\RedirectResponse;

class KompenResponHubActivityRetentionController extends Controller
{
    public function update(
        UpdateKompenResponHubActivityRetentionRequest $request,
        SiAdminProxyAccess $access,
        RecordKompenResponHubActivity $activity,
    ): RedirectResponse {
        $settings = KompenResponHubSystemSetting::current();
        $previousRetentionDays = $settings->activity_log_retention_days;
        $retentionDays = $request->integer('activity_log_retention_days');

        $settings->update(['activity_log_retention_days' => $retentionDays]);

        $actor = $access->actor($request);
        $admin = KompenResponHubAdmin::query()->find($actor['id']);
        $activity->execute(
            'maintenance.activity_log_retention_updated',
            'system_setting',
            'activity_log_retention',
            $admin,
            $request,
            reason: "Retensi riwayat aktivitas diubah menjadi {$retentionDays} hari.",
            beforeState: ['activity_log_retention_days' => $previousRetentionDays],
            afterState: ['activity_log_retention_days' => $retentionDays],
            subjectName: 'Retensi riwayat aktivitas',
        );

        return back()->with('success', 'Retensi riwayat aktivitas berhasil diperbarui.');
    }
}
