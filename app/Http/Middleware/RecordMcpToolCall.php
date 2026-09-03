<?php

namespace App\Http\Middleware;

use App\Models\McpToolCall;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes one audit row per request to /mcp: the user, the token and the app
 * it belongs to, the JSON-RPC method and tool, the HTTP status and duration.
 * Declared before authentication in the route's middleware, so a refused
 * request is recorded too (with no user); after $next returns, the guard
 * has resolved the user and token for an accepted one.
 *
 * Deliberately records nothing from the payload beyond the tool's name — an
 * organizer's draft text is not audit material — and never lets a logging
 * failure fail the request.
 */
class RecordMcpToolCall
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);

        $response = $next($request);

        try {
            $body = $request->json()->all();
            // A JSON-RPC batch is an array of calls; record it as one. (An empty
            // or non-JSON body decodes to [] too, and is not a batch.)
            $isBatch = $body !== [] && array_is_list($body);
            $user = $request->user();
            $token = $user?->token();

            McpToolCall::create([
                'user_id' => $user?->getAuthIdentifier(),
                'token_id' => $token?->id ?? null,
                'client_name' => $token?->client?->name,
                'method' => $isBatch ? 'batch' : substr((string) ($body['method'] ?? '?'), 0, 64),
                'tool' => $isBatch ? null : (isset($body['params']['name']) ? substr((string) $body['params']['name'], 0, 64) : null),
                'status' => $response->getStatusCode(),
                'duration_ms' => (int) ((hrtime(true) - $started) / 1e6),
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('MCP audit row not written: '.$e->getMessage());
        }

        return $response;
    }
}
