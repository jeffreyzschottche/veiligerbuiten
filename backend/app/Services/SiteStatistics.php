<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/** A durable daily aggregate; never publish individual account data. */
class SiteStatistics
{
    private const KEY = 'site:community:snapshot:v1';
    private const BASELINE = 976;

    public function snapshot(): array
    {
        return $this->publish(force: false);
    }

    public function publish(bool $force = true): array
    {
        // Explicit database store: the snapshot survives restarts and deploys.
        $store = Cache::store('database');
        $today = now('Europe/Amsterdam')->toDateString();
        $snapshot = $store->get(self::KEY);
        if (! $force && is_array($snapshot) && ($snapshot['date'] ?? null) === $today) {
            return $snapshot['data'];
        }

        return $store->lock(self::KEY.':lock', 15)->block(3, function () use ($store, $force, $today) {
            // Another process may already have published today's count.
            $snapshot = $store->get(self::KEY);
            if (! $force && is_array($snapshot) && ($snapshot['date'] ?? null) === $today) {
                return $snapshot['data'];
            }

            $data = [
                'total' => self::BASELINE + User::count(),
                'updated_at' => now()->utc()->toIso8601String(),
            ];
            $store->forever(self::KEY, ['date' => $today, 'data' => $data]);

            return $data;
        });
    }
}
