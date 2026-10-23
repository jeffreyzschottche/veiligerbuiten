<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Installation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Een installatie is één toestel: het pushtoken en de betrouwbaarheidsscore.
 * Ingelogd wordt het toestel aan het account gekoppeld, zodat meldingen voor
 * je plekken op al je toestellen binnenkomen. Locaties worden niet opgeslagen.
 */
class InstallationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $installation = Installation::create(['token' => Str::random(64), 'user_id' => auth('sanctum')->id(), 'trust_score' => 50, 'last_seen_at' => now()]);

        return response()->json(['data' => ['token' => $installation->token]], 201);
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
        $installation->update(['push_token' => $data['push_token'], 'push_platform' => $data['platform'], 'user_id' => auth('sanctum')->id() ?? $installation->user_id, 'last_seen_at' => now()]);

        return response()->json(['data' => ['registered' => true]]);
    }

    /** Koppelt (of ontkoppelt bij uitloggen) dit toestel aan het ingelogde account. */
    public function link(Request $request): JsonResponse
    {
        $installation = Installation::where('token', $request->header('X-Installation-Token'))->firstOrFail();
        $installation->update(['user_id' => $request->boolean('unlink') ? null : $request->user()->id, 'last_seen_at' => now()]);

        return response()->json(['data' => ['linked' => $installation->user_id !== null]]);
    }
}
