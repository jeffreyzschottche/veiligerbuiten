<?php

namespace App\Services;

use App\Models\OpsEvent;
use Throwable;

/**
 * Houdt belangrijke gebeurtenissen bij voor de beheer-CMS (Zschot CMS haalt ze op via
 * /api/ops/status). Dezelfde gebeurtenis binnen een uur telt op in plaats van een nieuwe
 * regel, zodat één fout die steeds terugkomt geen honderd meldingen geeft.
 *
 * Geen persoonsgegevens: accounts alleen met hun nummer, nooit met naam of e-mailadres.
 * Mag nooit zelf iets laten mislukken: lukt opslaan niet, dan gaat de app gewoon door.
 */
class Ops
{
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    public static function record(string $type, string $level, string $title, ?string $message = null, array $data = [], ?string $dedupeKey = null): ?OpsEvent
    {
        try {
            $fingerprint = $dedupeKey === null ? null : hash('sha256', "{$type}|{$dedupeKey}");
            if ($fingerprint && ($existing = OpsEvent::query()->where('fingerprint', $fingerprint)->where('last_at', '>=', now()->subHour())->latest('id')->first())) {
                $existing->forceFill(['occurrences' => $existing->occurrences + 1, 'last_at' => now(), 'message' => self::clip($message, 1000)])->save();

                return $existing;
            }

            return OpsEvent::create([
                'type' => $type,
                'level' => $level,
                'title' => self::clip($title, 160),
                'message' => self::clip($message, 1000),
                'data' => $data ?: null,
                'fingerprint' => $fingerprint,
                'last_at' => now(),
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    /** Een onverwachte fout in de app; dezelfde fout op dezelfde plek telt een uur lang op. */
    public static function exception(Throwable $exception): void
    {
        $where = basename($exception->getFile()).':'.$exception->getLine();
        self::record('app.error', self::ERROR, class_basename($exception).' in '.$where, $exception->getMessage(), [
            'class' => $exception::class,
            'file' => str_replace(base_path().'/', '', $exception->getFile()),
            'line' => $exception->getLine(),
            'url' => app()->runningInConsole() ? null : request()->path(),
        ], $exception::class.'|'.$where);
    }

    private static function clip(?string $value, int $length): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $length);
    }
}
