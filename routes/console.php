<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Pending reservations expire after config('orders.reservation_ttl_hours')
 * (24 hours by default). The command is idempotent, locks the order and
 * reservation rows, and can safely overlap with an admin action. The
 * withoutOverlapping() lock avoids needless duplicate runs on one host;
 * cPanel/cron wiring is a later deployment task.
 */
Schedule::command('orders:expire-reservations')
    ->everyTenMinutes()
    ->withoutOverlapping(10);
