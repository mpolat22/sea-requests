<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rfqs:auto-close-overdue')
    ->dailyAt('00:10')
    ->timezone('Europe/Istanbul')
    ->withoutOverlapping();
