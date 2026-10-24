<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SiteStatistics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteStatisticsController extends Controller
{
    public function __invoke(Request $request, SiteStatistics $statistics): JsonResponse
    {
        $secret = (string) config('veiligbuiten.site_stats_token');
        abort_if($secret === '', 503);
        abort_unless(hash_equals($secret, (string) $request->bearerToken()), 404);

        return response()->json($statistics->snapshot())->header('Cache-Control', 'private, no-store');
    }
}
