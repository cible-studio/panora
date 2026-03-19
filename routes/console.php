<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule::command('reservations:sync-expired')->hourly();
// Libération panneaux expirés — chaque nuit à minuit
Schedule::command('reservations:sync-expired')->dailyAt('00:05');
Schedule::command('campaigns:sync-expired')->dailyAt('00:10');
// Expiration options — chaque nuit à 3h (options expirant dans 7 jours)
Schedule::command('reservations:expire-options --days=7')->dailyAt('03:00');


