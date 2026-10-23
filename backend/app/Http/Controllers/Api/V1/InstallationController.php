<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Installation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstallationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $installation = Installation::create(['token' => Str::random(64), 'alert_preferences' => ['direct' => true, 'warning' => true, 'info' => false], 'trust_score' => 50, 'last_seen_at' => now()]);
        return response()->json(['data' => ['token' => $installation->token, 'alert_preferences' => $installation->alert_preferences]], 201);
    }

    public function preferences(Request $request): JsonResponse
    {
        $installation = Installation::where('token', $request->header('X-Installation-Token'))->firstOrFail();
        $data = $request->validate(['preferences' => 'required|array']);
        $installation->update(['alert_preferences' => $data['preferences'], 'last_seen_at' => now()]);
        return response()->json(['data' => $installation->alert_preferences]);
    }

    public function pushToken(Request $request): JsonResponse
    {
        $installation = Installation::where('token', $request->header('X-Installation-Token'))->firstOrFail();
        $data = $request->validate([
            'push_token' => 'required|string|min:20|max:512',
            'platform' => 'required|in:android,ios',
        ]);
        // A restored app or a reinstalled device can move a token to a new anonymous installation.
        Installation::query()->where('push_token', $data['push_token'])->whereKeyNot($installation->id)
            ->update(['push_token' => null, 'push_platform' => null]);
        $installation->update(['push_token' => $data['push_token'], 'push_platform' => $data['platform'], 'last_seen_at' => now()]);
        return response()->json(['data' => ['registered' => true]]);
    }

    public function location(Request $request): JsonResponse
    {
        $installation = Installation::where('token', $request->header('X-Installation-Token'))->firstOrFail();
        $data = $request->validate([
            'latitude' => 'required|numeric|between:50.5,53.7', 'longitude' => 'required|numeric|between:3.1,7.3',
            'accuracy_m' => 'nullable|numeric|between:0,5000',
        ]);
        $installation->update([
            'last_latitude' => $data['latitude'], 'last_longitude' => $data['longitude'],
            'last_location_accuracy_m' => isset($data['accuracy_m']) ? min(5000, (int) $data['accuracy_m']) : null,
            'last_location_at' => now(), 'last_seen_at' => now(),
        ]);
        return response()->json(['data' => ['updated' => true]]);
    }
}
