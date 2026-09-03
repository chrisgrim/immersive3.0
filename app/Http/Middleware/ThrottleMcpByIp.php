<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * A per-IP limit on /mcp that runs BEFORE authentication.
 *
 * Laravel's middleware priority sorts `throttle` after `auth`, so the named
 * `mcp` limiter only ever sees requests that already authenticated — a
 * flood of bad tokens never reached it. This class is not a ThrottleRequests
 * subclass, so the sorter leaves it where it is declared: first.
 */
class ThrottleMcpByIp
{
    public const MAX_PER_MINUTE = 120;

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'mcp-ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_MINUTE)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32000, 'message' => 'Too many requests.'],
            ], 429)->header('Retry-After', (string) RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
