<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /** Lightweight readiness check for the load balancer and deployment pipeline. */
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
            Cache::put('veiligbuiten:healthcheck', now()->timestamp, 60);
            return response()->json(['status' => 'ok', 'checks' => ['database' => 'ok', 'cache' => 'ok']]);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['status' => 'degraded'], 503);
        }
    }
}
