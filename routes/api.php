<?php

use App\Http\Controllers\KompenResponHubController;
use App\Http\Controllers\KompenResponHubOutboundApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('sikompen/v1')->middleware(['throttle:sikompen-api-auth', 'sikompen.api-client', 'throttle:sikompen-api'])->group(function (): void {
    Route::get('mahasiswa/{nim}', [KompenResponHubOutboundApiController::class, 'student'])
        ->where('nim', '[0-9]{6,32}')
        ->name('api.sikompen.v1.students.show');
    Route::post('sync-runs', [KompenResponHubOutboundApiController::class, 'storeSyncRun'])
        ->name('api.sikompen.v1.sync-runs.store');
    Route::get('sync-runs/{runId}', [KompenResponHubOutboundApiController::class, 'showSyncRun'])
        ->whereUlid('runId')
        ->name('api.sikompen.v1.sync-runs.show');
    Route::get('sync-runs/{runId}/download', [KompenResponHubOutboundApiController::class, 'downloadSyncRun'])
        ->whereUlid('runId')
        ->name('api.sikompen.v1.sync-runs.download');
});

Route::prefix('kompen-respon')->middleware([
    'web',
    'sikompen.student-password-changed',
    'sikompen.data-access',
    'throttle:sikompen-data',
])->group(function (): void {
    Route::get('filter-options', [KompenResponHubController::class, 'filterOptions'])
        ->name('api.kompen-respon.filter-options');
    Route::get('students', [KompenResponHubController::class, 'students'])
        ->name('api.kompen-respon.students.index');
    Route::get('students/{student}', [KompenResponHubController::class, 'student'])
        ->name('api.kompen-respon.students.show');
    Route::get('details', [KompenResponHubController::class, 'details'])
        ->name('api.kompen-respon.details.index');
});
