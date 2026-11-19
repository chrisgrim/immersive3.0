<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages are laid out differently for phones and computers on the server
 * (Browser::isMobile()), so every page says it varies by browser. Google asks
 * for this when one address serves two layouts, and it keeps any cache in
 * between from handing the phone layout to a computer.
 */
class VaryByUserAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->setVary('User-Agent', false);
        }

        return $response;
    }
}
