<?php

use App\Http\Controllers\AdminAuthenticationController;
use App\Http\Controllers\AdminCredentialsController;
use App\Http\Controllers\AdminPasswordResetController;
use App\Http\Controllers\EmergencyAdminRecoveryController;
use App\Http\Controllers\KompenResponHubActiveStudentImportController;
use App\Http\Controllers\KompenResponHubActivityRetentionController;
use App\Http\Controllers\KompenResponHubAdminSetupController;
use App\Http\Controllers\KompenResponHubController;
use App\Http\Controllers\KompenResponHubExportController;
use App\Http\Controllers\KompenResponHubImportActivationController;
use App\Http\Controllers\KompenResponHubImportController;
use App\Http\Controllers\KompenResponHubImportTaskController;
use App\Http\Controllers\KompenResponHubLandingController;
use App\Http\Controllers\KompenResponHubLifecycleController;
use App\Http\Controllers\StudentAuthenticationController;
use App\Http\Controllers\StudentPasswordController;
use App\Http\Controllers\StudentPasswordResetController;
use App\Http\Controllers\StudentPasswordResetManagementController;
use App\Http\Controllers\StudentProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', KompenResponHubLandingController::class)->name('home');

Route::middleware('guest:admin')->group(function (): void {
    Route::get('admin/login', [AdminAuthenticationController::class, 'create'])->name('login');
    Route::post('admin/login', [AdminAuthenticationController::class, 'store'])
        ->middleware('throttle:admin-login')
        ->name('admin.login.store');
    Route::get('admin/lupa-password', [AdminPasswordResetController::class, 'createRequest'])->name('admin.password-reset.request');
    Route::get('admin/reset-password', [AdminPasswordResetController::class, 'edit'])->name('admin.password-reset.edit');
    Route::put('admin/reset-password', [AdminPasswordResetController::class, 'update'])
        ->middleware('throttle:admin-password-reset-confirm')->name('admin.password-reset.update');
});

Route::post('admin/lupa-password', [AdminPasswordResetController::class, 'storeRequest'])
    ->middleware('throttle:admin-password-reset-request')->name('admin.password-reset.request.store');

Route::middleware('guest:student')->group(function (): void {
    Route::get('mahasiswa/login', [StudentAuthenticationController::class, 'create'])->name('student.login');
    Route::post('mahasiswa/login', [StudentAuthenticationController::class, 'store'])
        ->middleware('throttle:student-login')
        ->name('student.login.store');
    Route::get('mahasiswa/lupa-password', [StudentPasswordResetController::class, 'createRequest'])->name('student.password-reset.request');
    Route::post('mahasiswa/lupa-password', [StudentPasswordResetController::class, 'storeRequest'])
        ->middleware('throttle:student-password-reset-request')->name('student.password-reset.request.store');
    Route::get('mahasiswa/reset-password', [StudentPasswordResetController::class, 'edit'])->name('student.password-reset.edit');
    Route::put('mahasiswa/reset-password', [StudentPasswordResetController::class, 'update'])
        ->middleware('throttle:student-password-reset-confirm')->name('student.password-reset.update');
});

Route::get('admin/setup', [KompenResponHubAdminSetupController::class, 'create'])->name('admin.setup.create');
Route::post('admin/setup', [KompenResponHubAdminSetupController::class, 'store'])
    ->middleware('throttle:admin-setup')
    ->name('admin.setup.store');

Route::middleware('sikompen.student')->group(function (): void {
    Route::middleware('sikompen.student-password-changed')->group(function (): void {
        Route::get('mahasiswa', [StudentProfileController::class, 'show'])
            ->name('student.kompen-respon.index');
        Route::get('kompen-respon', [StudentProfileController::class, 'show'])
            ->name('kompen-respon-hub.index');
    });

    Route::get('mahasiswa/ganti-password', [StudentPasswordController::class, 'edit'])
        ->name('student.password.edit');
    Route::put('mahasiswa/ganti-password', [StudentPasswordController::class, 'update'])
        ->middleware('throttle:10,15')
        ->name('student.password.update');
    Route::get('mahasiswa/profil', [StudentProfileController::class, 'show'])
        ->middleware('sikompen.student-password-changed')
        ->name('student.profile.show');
    Route::post('mahasiswa/logout', [StudentAuthenticationController::class, 'destroy'])
        ->name('student.logout');
});

Route::middleware(['sikompen.student-password-changed', 'sikompen.data-access'])->group(function (): void {
    Route::post('exports', [KompenResponHubExportController::class, 'store'])
        ->middleware('throttle:10,15')
        ->name('kompen-respon.exports.store');
    Route::get('exports/{exportTask}', [KompenResponHubExportController::class, 'show'])
        ->name('kompen-respon.exports.show');
    Route::get('exports/{exportTask}/download', [KompenResponHubExportController::class, 'download'])
        ->name('kompen-respon.exports.download');
    Route::delete('exports/{exportTask}', [KompenResponHubExportController::class, 'destroy'])
        ->middleware('throttle:10,15')
        ->name('kompen-respon.exports.destroy');
    Route::post('exports/{exportTask}/dismiss', [KompenResponHubExportController::class, 'dismiss'])
        ->middleware('throttle:20,1')
        ->name('kompen-respon.exports.dismiss');
});

Route::middleware(['sikompen.admin', 'sikompen.emergency-admin'])
    ->prefix('admin/recovery')
    ->name('admin.recovery.')
    ->group(function (): void {
        Route::get('/', [EmergencyAdminRecoveryController::class, 'index'])->name('index');
        Route::post('password-resets/{resetCode}/code', [EmergencyAdminRecoveryController::class, 'storeCode'])
            ->middleware('throttle:10,1')
            ->name('password-resets.code.store');
        Route::delete('password-resets/{resetCode}', [EmergencyAdminRecoveryController::class, 'destroyCode'])
            ->middleware('throttle:10,1')
            ->name('password-resets.destroy');
    });

Route::middleware(['sikompen.admin', 'sikompen.standard-admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [KompenResponHubController::class, 'adminIndex'])
        ->name('kompen-respon.index');
    Route::get('kompen-respon', fn () => to_route('admin.kompen-respon.index'))
        ->name('kompen-respon.legacy');
    Route::get('kompen-respon/student-search', [KompenResponHubController::class, 'adminStudentSearch'])
        ->middleware('throttle:30,1')
        ->name('kompen-respon.students.search');
    Route::get('kompen-respon/students/{student}/overview', [KompenResponHubController::class, 'adminStudentOverview'])
        ->middleware('throttle:sikompen-data')
        ->name('kompen-respon.students.overview');
    Route::get('kompen-respon/template', [KompenResponHubImportController::class, 'downloadTemplate'])
        ->name('kompen-respon.template.download');
    Route::get('kompen-respon/active-students/template', [KompenResponHubActiveStudentImportController::class, 'downloadTemplate'])
        ->name('kompen-respon.active-students.template.download');
    Route::post('kompen-respon/active-students/imports', [KompenResponHubActiveStudentImportController::class, 'store'])
        ->middleware('throttle:10,15')
        ->name('kompen-respon.active-students.imports.store');
    Route::get('kompen-respon/active-students/imports/{import}/download', [KompenResponHubActiveStudentImportController::class, 'downloadUploadedWorkbook'])
        ->name('kompen-respon.active-students.imports.download');
    Route::post('kompen-respon/active-students/imports/{import}/activate', [KompenResponHubActiveStudentImportController::class, 'activate'])
        ->middleware('throttle:20,1')
        ->name('kompen-respon.active-students.imports.activate');
    Route::delete('kompen-respon/active-students/imports/{import}', [KompenResponHubActiveStudentImportController::class, 'destroy'])
        ->middleware('throttle:10,1')
        ->name('kompen-respon.active-students.imports.destroy');
    Route::get('kompen-respon/active-students/import-tasks/{task}', [KompenResponHubActiveStudentImportController::class, 'showTask'])
        ->name('kompen-respon.active-students.import-tasks.show');
    Route::post('kompen-respon/imports', [KompenResponHubImportController::class, 'store'])
        ->middleware('throttle:10,15')
        ->name('kompen-respon.imports.store');
    Route::get('kompen-respon/imports/{import}/download', [KompenResponHubImportController::class, 'downloadUploadedWorkbook'])
        ->name('kompen-respon.imports.download');
    Route::put('kompen-respon/imports/{import}', [KompenResponHubImportController::class, 'update'])
        ->middleware('throttle:20,1')
        ->name('kompen-respon.imports.update');
    Route::delete('kompen-respon/imports/{import}', [KompenResponHubImportController::class, 'destroy'])
        ->middleware('throttle:10,15')
        ->name('kompen-respon.imports.destroy');
    Route::post('kompen-respon/imports/{import}/activate', [KompenResponHubImportActivationController::class, 'store'])
        ->middleware('throttle:5,15')
        ->name('kompen-respon.imports.activate');
    Route::delete('kompen-respon/imports/{import}/activation', [KompenResponHubImportActivationController::class, 'destroy'])
        ->middleware('throttle:5,15')
        ->name('kompen-respon.imports.activation.destroy');
    Route::get('kompen-respon/import-tasks/{importTask}', [KompenResponHubImportTaskController::class, 'show'])
        ->name('kompen-respon.import-tasks.show');
    Route::put('kompen-respon/cutoffs', [KompenResponHubLifecycleController::class, 'storeCutoff'])
        ->middleware('throttle:20,1')
        ->name('kompen-respon.cutoffs.store');
    Route::post('kompen-respon/cutoffs/{cutoff}/finalize', [KompenResponHubLifecycleController::class, 'finalizeCutoff'])
        ->middleware('throttle:5,15')
        ->name('kompen-respon.cutoffs.finalize');
    Route::delete('kompen-respon/cutoffs/{cutoff}/finalization', [KompenResponHubLifecycleController::class, 'rollbackCutoffFinalization'])
        ->middleware('throttle:5,15')
        ->name('kompen-respon.cutoffs.finalization.destroy');
    Route::put('kompen-respon/students/{student}/progress', [KompenResponHubLifecycleController::class, 'storeProgress'])
        ->middleware('throttle:30,1')
        ->name('kompen-respon.students.progress.store');
    Route::put('kompen-respon/students/{student}/summary-override', [KompenResponHubLifecycleController::class, 'storeSummaryOverride'])
        ->middleware('throttle:30,1')
        ->name('kompen-respon.students.summary-override.store');
    Route::put('kompen-respon/details/{detail}/override', [KompenResponHubLifecycleController::class, 'storeDetailOverride'])
        ->middleware('throttle:30,1')
        ->name('kompen-respon.details.override.store');
    Route::put('kompen-respon/warnings/{warning}', [KompenResponHubLifecycleController::class, 'updateWarning'])
        ->middleware('throttle:20,1')
        ->name('kompen-respon.warnings.update');
    Route::delete('kompen-respon/warnings/{warning}', [KompenResponHubLifecycleController::class, 'destroyWarning'])
        ->middleware('throttle:20,1')
        ->name('kompen-respon.warnings.destroy');
    Route::put('kompen-respon/activity-retention', [KompenResponHubActivityRetentionController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('kompen-respon.activity-retention.update');
    Route::put('kompen-respon/import-audit-retention', [KompenResponHubActivityRetentionController::class, 'updateImportAudit'])
        ->middleware('throttle:10,1')
        ->name('kompen-respon.import-audit-retention.update');
    Route::put('kompen-respon/workbook-recycle-bin-retention', [KompenResponHubActivityRetentionController::class, 'updateWorkbookRecycleBin'])
        ->middleware('throttle:10,1')
        ->name('kompen-respon.workbook-recycle-bin-retention.update');
    Route::post('kompen-respon/imports/{import}/restore', [KompenResponHubImportController::class, 'restore'])
        ->middleware('throttle:10,1')
        ->name('kompen-respon.imports.restore');
    Route::post('kompen-respon/active-students/imports/{import}/restore', [KompenResponHubActiveStudentImportController::class, 'restore'])
        ->middleware('throttle:10,1')
        ->name('kompen-respon.active-students.imports.restore');
    Route::get('settings', [KompenResponHubAdminSetupController::class, 'settings'])->name('settings');
    Route::get('student-password-resets', [StudentPasswordResetManagementController::class, 'index'])
        ->name('student-password-resets.index');
    Route::post('student-password-resets/{studentPasswordReset}/refresh', [StudentPasswordResetManagementController::class, 'refresh'])
        ->middleware('throttle:20,1')
        ->name('student-password-resets.refresh');
    Route::delete('student-password-resets/{studentPasswordReset}', [StudentPasswordResetManagementController::class, 'destroy'])
        ->middleware('throttle:20,1')
        ->name('student-password-resets.destroy');
    Route::put('settings/username', [AdminCredentialsController::class, 'updateUsername'])
        ->middleware('throttle:10,15')->name('settings.username.update');
    Route::put('settings/password', [AdminCredentialsController::class, 'updatePassword'])
        ->middleware('throttle:10,15')->name('settings.password.update');
    Route::post('settings/admin-setup', [KompenResponHubAdminSetupController::class, 'enable'])
        ->name('settings.admin-setup.enable');
    Route::delete('settings/admin-setup', [KompenResponHubAdminSetupController::class, 'disable'])
        ->name('settings.admin-setup.disable');
});

Route::middleware('sikompen.admin')->prefix('admin')->name('admin.')->group(function (): void {
    Route::post('logout', [AdminAuthenticationController::class, 'destroy'])->name('logout');
});
