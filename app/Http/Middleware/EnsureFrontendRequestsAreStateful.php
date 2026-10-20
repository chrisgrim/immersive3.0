<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful as SanctumEnsureFrontendRequestsAreStateful;

/**
 * Sanctum decides "this API call comes from our own pages" by reading the
 * Referer (or Origin) header. A browser that sends neither (Safari with
 * Advanced Tracking and Fingerprinting Protection, strict privacy
 * extensions) got every logged-in API call answered 401: an admin saw an
 * empty dashboard while the page itself, a normal web request, loaded fine.
 *
 * Sec-Fetch-Site is set by the browser itself (scripts cannot change it),
 * so "same-origin" is the same answer the Referer would have given. Nothing
 * is loosened: the session cookie and the CSRF check still apply.
 */
class EnsureFrontendRequestsAreStateful extends SanctumEnsureFrontendRequestsAreStateful
{
    public static function fromFrontend($request)
    {
        return parent::fromFrontend($request)
            || $request->headers->get('sec-fetch-site') === 'same-origin';
    }
}
