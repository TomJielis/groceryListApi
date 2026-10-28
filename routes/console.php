<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Personal data (IP addresses, email logs) is kept no longer than the privacy policy promises.
// Needs the scheduler on the server: * * * * * cd /path/to/api && php artisan schedule:run
Schedule::command('privacy:prune')->dailyAt('03:30');
