<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\KompenResponHub\SikompenAdminAccess;
use App\Http\Requests\UpdateKompenResponHubActivityRetentionRequest;
use App\Http\Requests\UpdateKompenResponHubImportAuditRetentionRequest;
use App\Http\Requests\UpdateKompenResponHubWorkbookRetentionRequest;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubSystemSetting;
use Illuminate\Http\RedirectResponse;

class KompenResponHubActivityRetentionController extends Controller
{
    public function update(
        UpdateKompenResponHubActivityRetentionRequest $request,
        SikompenAdminAccess $access,
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

    public function updateImportAudit(
        UpdateKompenResponHubImportAuditRetentionRequest $request,
        SikompenAdminAccess $access,
        RecordKompenResponHubActivity $activity,
    ): RedirectResponse {
        $settings = KompenResponHubSystemSetting::current();
        $previousRetentionDays = $settings->import_audit_log_retention_days;
        $retentionDays = $request->integer('import_audit_log_retention_days');

        $settings->update(['import_audit_log_retention_days' => $retentionDays]);

        $actor = $access->actor($request);
        $admin = is_int($actor['id'])
            ? KompenResponHubAdmin::query()->find($actor['id'])
            : null;
        $activity->execute(
            'maintenance.import_audit_log_retention_updated',
            'system_setting',
            'import_audit_log_retention',
            $admin,
            $request,
            reason: "Retensi Log Upload diubah menjadi {$retentionDays} hari.",
            beforeState: ['import_audit_log_retention_days' => $previousRetentionDays],
            afterState: ['import_audit_log_retention_days' => $retentionDays],
            subjectName: 'Retensi Log Upload',
        );

        return back()->with('success', 'Retensi Log Upload berhasil diperbarui.');
    }

    public function updateWorkbookRecycleBin(
        UpdateKompenResponHubWorkbookRetentionRequest $request,
        SikompenAdminAccess $access,
        RecordKompenResponHubActivity $activity,
    ): RedirectResponse {
        $settings = KompenResponHubSystemSetting::current();
        $previousRetentionDays = $settings->workbook_recycle_bin_retention_days;
        $retentionDays = $request->integer('workbook_recycle_bin_retention_days');

        $settings->update(['workbook_recycle_bin_retention_days' => $retentionDays]);

        $actor = $access->actor($request);
        $admin = is_int($actor['id'])
            ? KompenResponHubAdmin::query()->find($actor['id'])
            : null;
        $activity->execute(
            'maintenance.workbook_recycle_bin_retention_updated',
            'system_setting',
            'workbook_recycle_bin_retention',
            $admin,
            $request,
            reason: "Retensi recycle bin workbook diubah menjadi {$retentionDays} hari.",
            beforeState: ['workbook_recycle_bin_retention_days' => $previousRetentionDays],
            afterState: ['workbook_recycle_bin_retention_days' => $retentionDays],
            subjectName: 'Retensi recycle bin workbook',
        );

        return back()->with('success', 'Retensi recycle bin workbook berhasil diperbarui.');
    }
}
