<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('veiligbuiten:expire-incidents')->everyFiveMinutes();
Schedule::command('veiligbuiten:prune-incidents')->everyTenMinutes();
Schedule::command('veiligbuiten:prune-installation-locations')->hourly()->withoutOverlapping();
Schedule::command('veiligbuiten:ingest-knmi')->hourly()->withoutOverlapping();
Schedule::command('veiligbuiten:ingest-ndw')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('veiligbuiten:ingest-luchtmeetnet')->hourly()->withoutOverlapping();
// Fifteen stations per run respects the public rate limit; the full national catalogue is built progressively.
Schedule::command('veiligbuiten:sync-luchtmeetnet-stations')->everyTenMinutes()->withoutOverlapping();
Schedule::command('veiligbuiten:ingest-rws-water')->hourly()->withoutOverlapping();
Schedule::command('veiligbuiten:ingest-police-news')->hourly()->withoutOverlapping();
// Zwaailicht cachet zelf 60 s; elke 2 minuten met If-None-Match is netjes en snel genoeg.
Schedule::command('veiligbuiten:ingest-p2000')->everyTwoMinutes()->withoutOverlapping();
Schedule::command('veiligbuiten:ingest-nl-alert')->everyTwoMinutes()->withoutOverlapping();
Schedule::command('veiligbuiten:ingest-knmi-quakes')->everyTenMinutes()->withoutOverlapping();
Schedule::command('veiligbuiten:sync-cbs-police-metadata')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('veiligbuiten:queue-alerts')->everyMinute()->withoutOverlapping();
Schedule::command('veiligbuiten:flush-digests')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('veiligbuiten:deliver-alerts')->everyMinute()->withoutOverlapping();
