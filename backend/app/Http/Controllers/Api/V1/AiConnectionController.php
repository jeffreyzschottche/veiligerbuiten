<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendAgentWebhook;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Koppeling met ChatGPT, Claude en andere assistenten (MCP) en eigen agents (webhook). */
class AiConnectionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user())]);
    }

    public function rotateToken(Request $request): JsonResponse
    {
        $request->user()->rotateAiToken();

        return response()->json(['data' => $this->payload($request->user()->fresh())]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $request->user()->forceFill(['ai_token' => null, 'ai_token_hash' => null])->save();

        return response()->json(['data' => $this->payload($request->user()->fresh())]);
    }

    public function updateWebhook(Request $request): JsonResponse
    {
        $data = $request->validate(['webhook_url' => 'nullable|url:https|max:500']);
        $user = $request->user();
        $url = $data['webhook_url'] ?? null;
        $user->forceFill([
            'webhook_url' => $url,
            // Een nieuw adres krijgt een nieuw geheim, zodat oude ontvangers niets meer kunnen verifiëren.
            'webhook_secret' => $url ? ($url === $user->webhook_url && $user->webhook_secret ? $user->webhook_secret : 'whsec_'.Str::random(40)) : null,
        ])->save();

        return response()->json(['data' => $this->payload($user->fresh())]);
    }

    public function testWebhook(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->webhook_url, 422, 'Er is nog geen webhook ingesteld.');
        SendAgentWebhook::dispatchSync($user->id, ['event' => 'test', 'message' => __('alerts.webhook_test', [], $user->locale)]);

        return response()->json(['data' => ['sent' => true]]);
    }

    private function payload(User $user): array
    {
        $base = rtrim(config('app.url'), '/').'/mcp';

        return [
            'mcp_url' => $user->ai_token ? "{$base}/{$user->ai_token}" : null,
            'mcp_endpoint' => $base,
            'token' => $user->ai_token,
            'webhook_url' => $user->webhook_url,
            'webhook_secret' => $user->webhook_secret,
        ];
    }
}
