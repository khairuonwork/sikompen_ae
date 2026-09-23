<?php

use App\Http\Controllers\AdminAuthenticationController;
use App\Http\Controllers\KompenResponHubAdminSetupController;
use App\Http\Controllers\KompenResponHubController;
use App\Http\Controllers\KompenResponHubDownloadController;
use App\Http\Controllers\KompenResponHubImportController;
use App\Http\Controllers\KompenResponHubImportRollbackController;
use App\Http\Controllers\KompenResponHubImportTaskController;
use App\Http\Controllers\KompenResponHubLandingController;
use App\Http\Controllers\KompenResponHubLifecycleController;
use App\Http\Controllers\SiAdminProxyAccessController;
use Illuminate\Support\Facades\Route;

Route::get('/', KompenResponHubLandingController::class)
    ->middleware('sikompen.proxy-session')
    ->name('home');

Route::get('si-admin/access', SiAdminProxyAccessController::class)
    ->middleware(['throttle:si-admin-proxy', 'si-admin.proxy'])
    ->name('si-admin.access');

Route::middleware('sikompen.standalone')->group(function (): void {
    Route::middleware('guest:admin')->group(function (): void {
        Route::get('admin/login', [AdminAuthenticationController::class, 'create'])->name('login');
        Route::post('admin/login', [AdminAuthenticationController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('admin.login.store');
    });

    Route::get('admin/setup', [KompenResponHubAdminSetupController::class, 'create'])->name('admin.setup.create');
    Route::post('admin/setup', [KompenResponHubAdminSetupController::class, 'store'])
        ->middleware('throttle:admin-setup')
        ->name('admin.setup.store');
});

Route::middleware('sikompen.proxy-session')->group(function (): void {
    Route::get('mahasiswa', [KompenResponHubController::class, 'studentIndex'])
        ->name('student.kompen-respon.index');
    Route::get('kompen-respon', [KompenResponHubController::class, 'studentIndex'])
        ->name('kompen-respon-hub.index');

    Route::prefix('mahasiswa/downloads')->name('student.kompen-respon.downloads.')->group(function (): void {
        Route::get('students', [KompenResponHubDownloadController::class, 'students'])->name('students');
        Route::get('students/pdf', [KompenResponHubDownloadController::class, 'studentsPdf'])->name('students.pdf');
        Route::get('details', [KompenResponHubDownloadController::class, 'details'])->name('details');
        Route::get('details/pdf', [KompenResponHubDownloadController::class, 'detailsPdf'])->name('details.pdf');
    });

    Route::middleware('sikompen.admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', [KompenResponHubController::class, 'adminIndex'])
            ->name('kompen-respon.index');
        Route::get('kompen-respon', fn () => to_route('admin.kompen-respon.index'))
            ->name('kompen-respon.legacy');
        Route::get('kompen-respon/template', [KompenResponHubImportController::class, 'downloadTemplate'])
            ->name('kompen-respon.template.download');
        Route::post('kompen-respon/imports', [KompenResponHubImportController::class, 'store'])
            ->middleware('throttle:10,15')
            ->name('kompen-respon.imports.store');
        Route::get('kompen-respon/imports/{import}/download', [KompenResponHubImportController::class, 'downloadUploadedWorkbook'])
            ->name('kompen-respon.imports.download');
        Route::delete('kompen-respon/imports/latest', [KompenResponHubImportRollbackController::class, 'destroy'])
            ->middleware('throttle:5,15')
            ->name('kompen-respon.imports.rollback');
        Route::get('kompen-respon/import-tasks/{importTask}', [KompenResponHubImportTaskController::class, 'show'])
            ->name('kompen-respon.import-tasks.show');
        Route::put('kompen-respon/cutoffs', [KompenResponHubLifecycleController::class, 'storeCutoff'])
            ->middleware('throttle:20,1')
            ->name('kompen-respon.cutoffs.store');
        Route::put('kompen-respon/students/{student}/progress', [KompenResponHubLifecycleController::class, 'storeProgress'])
            ->middleware('throttle:30,1')
            ->name('kompen-respon.students.progress.store');
        Route::put('kompen-respon/students/{student}/summary-override', [KompenResponHubLifecycleController::class, 'storeSummaryOverride'])
            ->middleware('throttle:30,1')
            ->name('kompen-respon.students.summary-override.store');
        Route::put('kompen-respon/details/{detail}/override', [KompenResponHubLifecycleController::class, 'storeDetailOverride'])
            ->middleware('throttle:30,1')
            ->name('kompen-respon.details.override.store');
        Route::post('kompen-respon/warnings', [KompenResponHubLifecycleController::class, 'storeWarning'])
            ->middleware('throttle:20,1')
            ->name('kompen-respon.warnings.store');
        Route::put('kompen-respon/warnings/{warning}', [KompenResponHubLifecycleController::class, 'updateWarning'])
            ->middleware('throttle:20,1')
            ->name('kompen-respon.warnings.update');
        Route::get('kompen-respon/downloads/warnings', [KompenResponHubDownloadController::class, 'warnings'])
            ->name('kompen-respon.downloads.warnings');
        Route::get('kompen-respon/downloads/warnings/pdf', [KompenResponHubDownloadController::class, 'warningsPdf'])
            ->name('kompen-respon.downloads.warnings.pdf');

        Route::middleware('sikompen.standalone')->group(function (): void {
            Route::get('settings', [KompenResponHubAdminSetupController::class, 'settings'])->name('settings');
            Route::post('settings/admin-setup', [KompenResponHubAdminSetupController::class, 'enable'])
                ->name('settings.admin-setup.enable');
            Route::delete('settings/admin-setup', [KompenResponHubAdminSetupController::class, 'disable'])
                ->name('settings.admin-setup.disable');
            Route::post('logout', [AdminAuthenticationController::class, 'destroy'])->name('logout');
        });
    });
});
