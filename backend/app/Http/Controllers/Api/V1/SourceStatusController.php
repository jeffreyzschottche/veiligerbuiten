<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SourceIngestionRun;
use Illuminate\Http\JsonResponse;

class SourceStatusController extends Controller
{
    /** Public, deliberately minimal source freshness information. No payloads or internal errors leak here. */
    public function __invoke(): JsonResponse
    {
        $definitions = config('services.source_health', []);
        $sources = collect($definitions)->map(function (array $definition, string $source) {
            $run = SourceIngestionRun::query()->where('source', $source)->latest('started_at')->first();
            $lastSuccess = SourceIngestionRun::query()->where('source', $source)->where('status', 'success')->latest('finished_at')->first();
            $maximumAge = (int) $definition['maximum_age_minutes'];
            $fresh = $lastSuccess?->finished_at?->greaterThan(now()->subMinutes($maximumAge));
            return [
                'source' => $source,
                'label' => $definition['label'],
                'enabled' => (bool) ($definition['enabled'] ?? true),
                'state' => !($definition['enabled'] ?? true) ? 'disabled' : ($run?->status === 'failed' ? 'degraded' : ($fresh ? 'healthy' : 'stale')),
                'lastSuccessfulAt' => $lastSuccess?->finished_at?->toIso8601String(),
            ];
        })->values();

        return response()->json(['data' => $sources]);
    }
}
