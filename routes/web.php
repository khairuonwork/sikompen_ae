<?php

use App\Http\Controllers\KompenResponHubController;
use App\Http\Controllers\KompenResponHubImportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/kompen-respon');

Route::get('kompen-respon', [KompenResponHubController::class, 'index'])
    ->name('kompen-respon-hub.index');

Route::get('kompen-respon/template', [KompenResponHubImportController::class, 'downloadTemplate'])
    ->name('kompen-respon-hub.template.download');

Route::post('kompen-respon/imports', [KompenResponHubImportController::class, 'store'])
    ->middleware('throttle:10,15')
    ->name('kompen-respon-hub.imports.store');
