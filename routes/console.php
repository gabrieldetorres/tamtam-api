<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reports:send-sales-email --due-only')
    ->dailyAt((string) config('tamtam.sales_report_email.send_time', '08:00'))
    ->timezone((string) config('tamtam.report_timezone', 'Asia/Manila'))
    ->withoutOverlapping();
