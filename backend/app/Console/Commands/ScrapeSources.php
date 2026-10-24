<?php

namespace App\Console\Commands;

use App\Services\ScrapePlanner;
use Illuminate\Console\Command;

class ScrapeSources extends Command
{
    protected $signature = 'veiligbuiten:scrape
                            {--lane=all : fast (P2000, NL-Alert), slow (de rest) of all}
                            {--force : Alle bronnen nu, ongeacht hun planning}
                            {--status : Toon per bron het tempo, de laatste ronde en fouten}';

    protected $description = 'Haal de bronnen op die aan de beurt zijn; het tempo past zich per bron aan.';

    public function handle(ScrapePlanner $planner): int
    {
        if ($this->option('status')) {
            $this->table(['Bron', 'Rij', 'Interval', 'Volgende', 'Laatst', 'Resultaat', 'Laatste verandering', 'Fouten'], collect($planner->status())->map(fn (array $s, string $name) => [
                $name,
                $s['lane'],
                isset($s['interval']) ? "{$s['interval']} min" : '-',
                isset($s['due']) ? now()->setTimestamp($s['due'])->diffForHumans() : 'nu',
                isset($s['last_at']) ? now()->setTimestamp($s['last_at'])->diffForHumans()." ({$s['last_ms']} ms)" : '-',
                ! isset($s['last_at']) ? '-' : (! $s['last_ok'] ? 'fout: '.mb_substr((string) $s['last_error'], 0, 60) : ($s['last_changed'] ? 'nieuw' : 'ongewijzigd')),
                isset($s['last_change_at']) ? now()->setTimestamp($s['last_change_at'])->diffForHumans() : '-',
                $s['failures'] ?? 0,
            ])->values()->all());

            return self::SUCCESS;
        }

        $results = $planner->run($this->option('lane'), (bool) $this->option('force'));
        $warmed = $results['_warmed'] ?? null;
        unset($results['_warmed']);
        foreach ($results as $name => $result) {
            $this->line(sprintf('%-20s %s, volgende over %s min', $name, ! $result['ok'] ? 'fout' : ($result['changed'] ? 'nieuw' : 'ongewijzigd'), $result['interval']));
        }
        if ($warmed !== null) $this->info("{$warmed} gebieden klaargezet.");

        // Een bron met een fout is geen mislukte ronde: die staat in het overzicht en wacht langer.
        return self::SUCCESS;
    }
}
