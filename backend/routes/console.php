<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Levenscyclus: verlopen meldingen archiveren, en oude archieven en logs echt verwijderen.
Schedule::command('veiligbuiten:expire-incidents')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('veiligbuiten:prune-incidents')->hourly()->withoutOverlapping();
// Clusters, niveau en bereik (veroudering) bijwerken; alleen echte veranderingen worden geschreven.
Schedule::command('veiligbuiten:risk-clusters')->everyTwoMinutes()->withoutOverlapping();
Schedule::command('veiligbuiten:hotspots')->dailyAt('04:10')->timezone('Europe/Amsterdam')->withoutOverlapping();
Schedule::command('veiligbuiten:prune-installation-locations')->hourly()->withoutOverlapping();
// Alle bronnen via de ophaalplanner: elke minuut kijkt hij welke bron aan de beurt is (tempo per bron,
// zie config veiligbuiten.scrape). Twee rijen naast elkaar, zodat een trage bron P2000 nooit ophoudt.
Schedule::command('veiligbuiten:scrape --lane=fast')->everyMinute()->withoutOverlapping(10)->runInBackground();
Schedule::command('veiligbuiten:scrape --lane=slow')->everyMinute()->withoutOverlapping(30)->runInBackground();
// Fifteen stations per run respects the public rate limit; the full national catalogue is built progressively.
Schedule::command('veiligbuiten:sync-luchtmeetnet-stations')->everyTenMinutes()->withoutOverlapping();
// Politiecijfers per buurt (CBS) verschijnen halverwege de maand; daarna één keer ophalen is genoeg.
Schedule::command('veiligbuiten:neighbourhood-crime')->monthlyOn(20, '05:20')->timezone('Europe/Amsterdam')->withoutOverlapping();
Schedule::command('veiligbuiten:queue-alerts')->everyMinute()->withoutOverlapping();
Schedule::command('veiligbuiten:flush-digests')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('veiligbuiten:deliver-alerts')->everyMinute()->withoutOverlapping();

Schedule::command('veiligbuiten:publish-site-statistics')->dailyAt('03:30')->timezone('Europe/Amsterdam')->withoutOverlapping();
