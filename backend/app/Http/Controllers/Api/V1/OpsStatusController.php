<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\OpsEvent;
use App\Models\User;
use App\Services\Ops;
use App\Services\ScrapePlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Status en gebeurtenissen voor de beheer-CMS (Zschot CMS, integratie 'App-status').
 * Beveiligd met OPS_TOKEN; zonder token bestaat dit endpoint niet (404).
 *
 * Contract: { status, message, checks[], metrics{}, events[], cursor }. De CMS stuurt
 * ?since=<cursor> mee en krijgt alleen nieuwe gebeurtenissen; staat de CMS een tijd uit,
 * dan mist hij niets (30 dagen bewaard).
 */
class OpsStatusController extends Controller
{
    private const STATUS_RANK = ['ok' => 0, 'warning' => 1, 'failed' => 2];

    public function __invoke(Request $request, ScrapePlanner $planner): JsonResponse
    {
        $token = (string) config('services.ops.token');
        abort_if($token === '', 404);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401);

        $checks = [$this->database(), $this->scheduler(), $this->sources($planner), $this->queue(), $this->errors()];
        $worst = collect($checks)->sortByDesc(fn (array $check) => self::STATUS_RANK[$check['status']])->first();
        $problems = collect($checks)->where('status', '!=', 'ok');

        $since = max(0, (int) $request->query('since', 0));
        $events = OpsEvent::query()->where('id', '>', $since)->orderBy('id')->limit(100)->get();

        return response()->json([
            'app' => 'VeiligerBuiten',
            'status' => $worst['status'],
            'message' => $problems->isEmpty() ? 'Alles draait.' : $problems->map(fn (array $c) => "{$c['label']}: {$c['message']}")->implode(' · '),
            'checks' => $checks,
            'metrics' => $this->metrics(),
            'events' => $events->map->present()->values(),
            'cursor' => $events->max('id') ?? $since,
        ]);
    }

    private function database(): array
    {
        try {
            DB::select('select 1');

            return $this->check('database', 'Database', 'ok', 'Bereikbaar.');
        } catch (\Throwable $e) {
            return $this->check('database', 'Database', 'failed', 'Niet bereikbaar.');
        }
    }

    /** Draait de scheduler nog? Elke ophaalronde zet een hartslag. */
    private function scheduler(): array
    {
        $beat = Cache::get('vb:heartbeat:fast');
        if (! $beat) {
            return $this->check('scheduler', 'Ophalen', 'failed', 'De scheduler heeft nog nooit gedraaid.');
        }
        $age = now()->getTimestamp() - (int) $beat;
        $status = $age <= 180 ? 'ok' : ($age <= 600 ? 'warning' : 'failed');

        return $this->check('scheduler', 'Ophalen', $status, $status === 'ok' ? 'Laatste ronde '.$age.' s geleden.' : 'Geen ophaalronde sinds '.round($age / 60).' minuten. Draait de scheduler?', ['seconds_ago' => $age]);
    }

    /** Bronnen met 3+ fouten op rij. Alle P2000-bronnen of NL-Alert stuk = ernstig. */
    private function sources(ScrapePlanner $planner): array
    {
        $status = collect($planner->status());
        $failing = $status->filter(fn (array $s) => ($s['failures'] ?? 0) >= 3);
        $p2000 = $status->filter(fn (array $s, string $name) => str_starts_with($name, 'p2000_'));
        $allP2000Down = $p2000->isNotEmpty() && $p2000->every(fn (array $s) => ($s['failures'] ?? 0) >= 3);
        $level = match (true) {
            $failing->isEmpty() => 'ok',
            $allP2000Down || $failing->has('nl_alert') => 'failed',
            default => 'warning',
        };

        return $this->check('sources', 'Bronnen', $level,
            $failing->isEmpty() ? $status->count().' bronnen werken.' : 'Faalt: '.$failing->keys()->implode(', ').'.',
            ['failing' => $failing->keys()->values(), 'total' => $status->count()]);
    }

    private function queue(): array
    {
        $failed = rescue(fn () => DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count(), 0, false);

        return $this->check('queue', 'Pushberichten', $failed > 0 ? 'warning' : 'ok', $failed > 0 ? "{$failed} mislukte taken in 24 uur." : 'Geen mislukte taken.', ['failed_24h' => $failed]);
    }

    private function errors(): array
    {
        $errors = (int) OpsEvent::query()->where('level', Ops::ERROR)->where('last_at', '>=', now()->subHour())->sum('occurrences');
        $status = $errors === 0 ? 'ok' : ($errors >= 20 ? 'failed' : 'warning');

        return $this->check('errors', 'Fouten', $status, $errors === 0 ? 'Geen fouten in het afgelopen uur.' : "{$errors} fout(en) in het afgelopen uur.", ['last_hour' => $errors]);
    }

    private function metrics(): array
    {
        return rescue(fn () => [
            'subscribers_active' => User::query()->whereIn('subscription_status', ['active', 'cancelled'])->where('subscription_expires_at', '>', now())->count(),
            'accounts_new_24h' => User::query()->where('created_at', '>=', now()->subDay())->count(),
            'incidents_live' => Incident::query()->where('visibility', 'public')->whereNull('resolved_at')->where('expires_at', '>', now())->count(),
            // Gebieden waar telefoons de laatste uren om vroegen: een ruwe maat voor gebruik.
            'areas_active' => count(Cache::get('vb:sync:cells', [])),
        ], [], false);
    }

    private function check(string $key, string $label, string $status, string $message, array $data = []): array
    {
        return compact('key', 'label', 'status', 'message', 'data');
    }
}
