<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the OAuth consent screen (GET/POST/DELETE /oauth/authorize).
 *
 * Three rules, in order:
 *  - While services.mcp.public is false, only moderators/admins may connect
 *    an assistant — the same soft launch the API keys page has.
 *  - The account's email must be verified.
 *  - An assistant may only be granted the default scope(s). `mcp:moderate`
 *    is refused here outright, so no OAuth grant can ever carry moderator
 *    powers (see User::credentialAllowsModeration); it exists only on
 *    personal access tokens a moderator creates deliberately.
 */
class EnsureMcpConsentAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! config('services.mcp.public') && ! $user?->isModerator()) {
            abort(403, 'Connecting an AI assistant is not yet available.');
        }

        if ($user?->email_verified_at === null) {
            abort(403, 'Verify your email address before connecting an AI assistant.');
        }

        $requested = array_values(array_filter(explode(' ', (string) $request->query('scope', ''))));
        $refused = array_diff($requested, Passport::defaultScopes());

        if ($refused !== []) {
            abort(400, 'The scope "'.implode(' ', $refused).'" cannot be granted to a connected app.');
        }

        return $next($request);
    }
}
