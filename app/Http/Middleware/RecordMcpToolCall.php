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
            [$method, $tool] = $this->describe($request);
            $user = $request->user();
            $token = $user?->token();

            McpToolCall::create([
                'user_id' => $user?->getAuthIdentifier(),
                'token_id' => $token?->id ?? null,
                'client_name' => $token?->client?->name,
                'method' => $method,
                'tool' => $tool,
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

    /**
     * The JSON-RPC method and tool name(s) of the request — a batch lists
     * every tool it called — or '?' when the body is not JSON-RPC at all,
     * so the row is still written.
     *
     * @return array{0: string, 1: string|null}
     */
    protected function describe(Request $request): array
    {
        try {
            $body = $request->json()->all();
        } catch (\Throwable) {
            return ['?', null];
        }

        if (! is_array($body) || $body === []) {
            return ['?', null];
        }

        $name = fn ($call) => is_array($call) && isset($call['params']['name']) && is_scalar($call['params']['name'])
            ? (string) $call['params']['name']
            : null;

        if (array_is_list($body)) {
            $tools = array_values(array_filter(array_map($name, $body)));

            return ['batch', $tools ? self::fit(implode(',', $tools), 255) : null];
        }

        $method = isset($body['method']) && is_scalar($body['method']) ? (string) $body['method'] : '?';

        return [self::fit($method, 64), ($tool = $name($body)) ? self::fit($tool, 255) : null];
    }

    /**
     * Fit a value into a column of $chars characters, multibyte-safe, marking
     * the cut rather than silently dropping the tail: a huge batch's later
     * tools were disappearing from the record.
     */
    protected static function fit(string $value, int $chars): string
    {
        return mb_strlen($value) <= $chars ? $value : mb_substr($value, 0, $chars - 8).' …(more)';
    }
}
