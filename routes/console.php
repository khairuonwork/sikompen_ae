<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sikompen:purge-exports')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('sikompen:purge-import-tasks')
    ->daily()
    ->withoutOverlapping();

Schedule::command('sikompen:purge-activity-logs')
    ->dailyAt('02:15')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30);

Schedule::command('sikompen:purge-import-audit-logs')
    ->dailyAt('02:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30);

Schedule::command('sikompen:purge-recycled-workbooks')
    ->daily()
    ->withoutOverlapping();

Schedule::command('sikompen:reconcile-tasks')
    ->everyFiveMinutes()
    ->withoutOverlapping();
