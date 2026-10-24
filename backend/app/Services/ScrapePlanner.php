<?php

namespace App\Services;

use App\Models\Incident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Beslist elke minuut welke bronnen aan de beurt zijn, en past het tempo per bron aan.
 *
 *  - Er veranderde iets: terug naar het snelste interval (min). Een drukke bron blijft vlot.
 *  - Niets veranderd (vaak een lege 304): het interval wordt ×1,5 ruimer, tot max.
 *  - Een fout of een bron die om een pauze vraagt: 2× langer wachten, tot backoff_max.
 *  - 's Nachts minstens min × factor, behalve bronnen die altijd snel moeten (NL-Alert).
 *  - Een beetje willekeur (jitter), zodat we niet op vaste tijden aankloppen.
 *
 * De toestand per bron staat in de cache. Raakt die kwijt, dan is elke bron meteen aan
 * de beurt en begint het tempo opnieuw bij min: niets gaat verloren.
 */
class ScrapePlanner
{
    public function __construct(private readonly AreaSync $sync) {}

    /** @return array<string, array> bron => resultaat van deze ronde (alleen bronnen die draaiden) */
    public function run(string $lane, bool $force = false): array
    {
        $results = [];
        foreach ($this->sources($lane) as $name => $source) {
            $state = $this->state($name);
            if (! $force && ($state['due'] ?? 0) > now()->getTimestamp()) continue;
            $results[$name] = $this->runSource($name, $source, $state);
        }

        // Is er iets veranderd, dan zetten we de antwoorden voor de telefoons nu al klaar.
        if (collect($results)->contains('changed', true)) {
            $this->sync->bump();
            $results['_warmed'] = $this->sync->warm();
        }

        return $results;
    }

    /** @return array<string, array> bron => toestand, voor het overzicht */
    public function status(): array
    {
        return collect($this->sources(null))->mapWithKeys(fn (array $source, string $name) => [$name => $this->state($name) + ['lane' => $source['lane']]])->all();
    }

    private function runSource(string $name, array $source, array $state): array
    {
        $before = $this->fingerprint();
        $started = microtime(true);
        try {
            $ok = Artisan::call($source['command'], $source['args'] ?? []) === 0;
            $error = $ok ? null : mb_substr(trim(Artisan::output()), -300);
        } catch (\Throwable $exception) {
            report($exception);
            [$ok, $error] = [false, mb_substr($exception->getMessage(), 0, 300)];
        }
        $changed = $ok && $this->fingerprint() !== $before;

        $config = config('veiligbuiten.scrape');
        $interval = (float) ($state['interval'] ?? $source['min']);
        $failures = $ok ? 0 : ($state['failures'] ?? 0) + 1;
        $interval = match (true) {
            ! $ok => min($config['backoff_max'], max($source['min'], $interval) * 2),
            $changed => $source['min'],
            default => min($source['max'], $interval * 1.5),
        };
        if (($source['night'] ?? true) && $this->isNight()) {
            $interval = max($interval, $source['min'] * $config['night']['factor']);
        }
        $seconds = $interval * 60 * (1 + (mt_rand(-1000, 1000) / 1000) * $config['jitter']);

        $state = [
            'interval' => round($interval, 2),
            'due' => now()->getTimestamp() + (int) max(50, $seconds),
            'failures' => $failures,
            'last_at' => now()->getTimestamp(),
            'last_ok' => $ok,
            'last_changed' => $changed,
            'last_error' => $error,
            'last_ms' => (int) round((microtime(true) - $started) * 1000),
            'last_change_at' => $changed ? now()->getTimestamp() : ($state['last_change_at'] ?? null),
        ];
        Cache::forever($this->key($name), $state);

        return ['ok' => $ok, 'changed' => $changed, 'interval' => $state['interval'], 'error' => $error];
    }

    /** @return array<string, array> */
    private function sources(?string $lane): array
    {
        return collect(config('veiligbuiten.scrape.sources'))
            ->filter(fn (array $source) => ($source['enabled'] ?? true) && ($lane === null || $lane === 'all' || $source['lane'] === $lane))
            ->all();
    }

    private function state(string $name): array
    {
        return Cache::get($this->key($name), []);
    }

    private function key(string $name): string
    {
        return "vb:scrape:{$name}";
    }

    /** Eén goedkope query: is er sinds de vorige keer iets aan de meldingen veranderd? */
    private function fingerprint(): string
    {
        $row = Incident::query()->toBase()->selectRaw('max(updated_at) as version, count(*) as total')->first();

        return "{$row->version}|{$row->total}";
    }

    private function isNight(): bool
    {
        $hour = (int) Carbon::now(config('veiligbuiten.timezone'))->format('G');
        ['from' => $from, 'to' => $to] = config('veiligbuiten.scrape.night');

        return $from <= $to ? $hour >= $from && $hour < $to : $hour >= $from || $hour < $to;
    }
}
