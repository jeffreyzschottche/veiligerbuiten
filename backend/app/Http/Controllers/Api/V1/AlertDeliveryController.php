<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AlertDelivery;
use App\Models\Installation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertDeliveryController extends Controller
{
    /** Record a notification opening without exposing another user's delivery. */
    public function opened(Request $request, AlertDelivery $alertDelivery): JsonResponse
    {
        $installation = Installation::where('token', $request->header('X-Installation-Token'))->firstOrFail();
        abort_unless($alertDelivery->installation_id === $installation->id, 404);

        $alertDelivery->update(['opened_at' => $alertDelivery->opened_at ?? now()]);
        $installation->update(['last_seen_at' => now()]);

        return response()->json(['data' => ['opened' => true]]);
    }
}
