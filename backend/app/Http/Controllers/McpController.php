<?php

namespace App\Http\Controllers;

use App\Mcp\VeiligBuitenTools;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Minimale MCP-server (Streamable HTTP, JSON-antwoorden, geen sessies).
 *
 * Authenticatie via het persoonlijke token in de URL (/mcp/{token}) of als
 * Bearer-token; zo werkt het als "custom connector" in ChatGPT, Claude en
 * andere MCP-clients zonder extra OAuth-stap.
 */
class McpController extends Controller
{
    private const PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    public function __construct(private readonly VeiligBuitenTools $tools) {}

    public function handle(Request $request, ?string $token = null): JsonResponse|Response
    {
        if ($request->isMethod('get')) {
            return response('', 405, ['Allow' => 'POST']);
        }

        $user = User::findByAiToken($token ?? $request->bearerToken());
        if (! $user) {
            return response()->json($this->error(null, -32001, __('mcp.invalid_token')), 401);
        }

        app()->setLocale($user->locale);
        Carbon::setLocale($user->locale);

        $payload = $request->json()->all();
        $isBatch = array_is_list($payload) && $payload !== [];
        $responses = array_values(array_filter(array_map(fn ($message) => $this->dispatch($user, (array) $message), $isBatch ? $payload : [$payload])));

        if ($responses === []) {
            return response('', 202);
        }

        return response()->json($isBatch ? $responses : $responses[0]);
    }

    private function dispatch(User $user, array $message): ?array
    {
        $id = $message['id'] ?? null;
        $method = $message['method'] ?? '';
        $params = $message['params'] ?? [];

        // Notificaties (zonder id) krijgen geen antwoord.
        if ($id === null) {
            return null;
        }

        return match ($method) {
            'initialize' => $this->result($id, [
                'protocolVersion' => in_array($params['protocolVersion'] ?? null, self::PROTOCOL_VERSIONS, true) ? $params['protocolVersion'] : self::PROTOCOL_VERSIONS[0],
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'veiligbuiten', 'title' => 'VeiligBuiten', 'version' => '1.0.0'],
                'instructions' => 'VeiligBuiten geeft actuele veiligheidssituaties in Nederland uit officiële bronnen en van gebruikers. Gebruik get_situations_nearby voor vragen als "is het rustig bij ...". Noem altijd de bron en hoe recent iets is. Bij direct gevaar: adviseer 112 te bellen.',
            ]),
            'ping' => $this->result($id, new \stdClass),
            'tools/list' => $this->result($id, ['tools' => $this->tools->definitions()]),
            'tools/call' => $this->callTool($user, $id, $params),
            default => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    private function callTool(User $user, mixed $id, array $params): array
    {
        if (! $user->hasActiveSubscription()) {
            return $this->result($id, ['content' => [['type' => 'text', 'text' => __('mcp.inactive')]], 'isError' => true]);
        }

        try {
            [$text, $structured] = $this->tools->call($user, (string) ($params['name'] ?? ''), (array) ($params['arguments'] ?? []));

            return $this->result($id, ['content' => [['type' => 'text', 'text' => $text]], 'structuredContent' => $structured, 'isError' => false]);
        } catch (InvalidArgumentException $exception) {
            return $this->result($id, ['content' => [['type' => 'text', 'text' => $exception->getMessage()]], 'isError' => true]);
        }
    }

    private function result(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
