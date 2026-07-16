<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the API-token management UI. While services.mcp.token_ui_public is
 * false the page is a moderator/admin preview; flipping the env var opens
 * it to all (verified) users without a code change.
 */
class EnsureCanManageApiTokens
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('services.mcp.token_ui_public') || $request->user()?->isModerator()) {
            return $next($request);
        }

        abort(403, 'API tokens are not yet available.');
    }
}
