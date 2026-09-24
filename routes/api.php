<?php

use App\Http\Controllers\KompenResponHubController;
use Illuminate\Support\Facades\Route;

Route::prefix('kompen-respon')->middleware(['web', 'sikompen.proxy-session', 'throttle:sikompen-data'])->group(function (): void {
    Route::get('filter-options', [KompenResponHubController::class, 'filterOptions'])
        ->name('api.kompen-respon.filter-options');
    Route::get('students', [KompenResponHubController::class, 'students'])
        ->name('api.kompen-respon.students.index');
    Route::get('students/{student}', [KompenResponHubController::class, 'student'])
        ->name('api.kompen-respon.students.show');
    Route::get('details', [KompenResponHubController::class, 'details'])
        ->name('api.kompen-respon.details.index');
});
