<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forbid embedding the response in any frame. On the OAuth consent screen
 * a hostile page could otherwise lay its own buttons over Approve and the
 * "include moderator powers" box and walk a signed-in moderator into
 * granting powers they never saw (clickjacking). Both headers: the CSP
 * directive is the modern one, X-Frame-Options covers older browsers.
 */
class DenyFraming
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        $response->headers->set('X-Frame-Options', 'DENY');

        return $response;
    }
}
