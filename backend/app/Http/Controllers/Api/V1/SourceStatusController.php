<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\SourceSwitch;
use App\Models\SourceIngestionRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SourceStatusController extends Controller
{
    /** Public, deliberately minimal source freshness information. No payloads or internal errors leak here. */
    public function __invoke(): JsonResponse
    {
        // Openbaar endpoint: een minuut cachen, zodat het geen gratis databasebelasting is.
        return response()->json(['data' => Cache::remember('vb:sources-status', 60, fn () => $this->sources())]);
    }

    private function sources(): array
    {
        $definitions = config('services.source_health', []);

        return collect($definitions)->map(function (array $definition, string $source) {
            $run = SourceIngestionRun::query()->where('source', $source)->latest('started_at')->first();
            $lastSuccess = SourceIngestionRun::query()->where('source', $source)->where('status', 'success')->latest('finished_at')->first();
            $maximumAge = (int) $definition['maximum_age_minutes'];
            $fresh = $lastSuccess?->finished_at?->greaterThan(now()->subMinutes($maximumAge));
            $enabled = ($definition['enabled'] ?? true) && SourceSwitch::enabled($definition['switch'] ?? $source);
            return [
                'source' => $source,
                'label' => $definition['label'],
                'enabled' => (bool) $enabled,
                'state' => ! $enabled ? 'disabled' : ($run?->status === 'failed' ? 'degraded' : ($fresh ? 'healthy' : 'stale')),
                'lastSuccessfulAt' => $lastSuccess?->finished_at?->toIso8601String(),
            ];
        })->values()->all();
    }
}
