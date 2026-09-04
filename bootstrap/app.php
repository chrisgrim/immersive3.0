<?php

use App\Console\Commands\ArchiveOldClicks;
use App\Console\Commands\CheckClosingEvents;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Add our custom middleware to block edit routes during maintenance
        $middleware->prepend(\App\Http\Middleware\BlockEditDuringMaintenance::class);

        // Records one row per session on its first authenticated request —
        // powers Account Settings' Login & Security device history. Web-only:
        // Sanctum SPA API requests share the same session cookie/id, so
        // recording it again there would be redundant, not additive.
        $middleware->web(append: [
            \App\Http\Middleware\RecordLoginHistory::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'moderator' => \App\Http\Middleware\ModeratorMiddleware::class,
            // Passport scope checks for the MCP endpoint (routes/ai.php).
            'scopes' => \Laravel\Passport\Http\Middleware\CheckToken::class,
            'scope' => \Laravel\Passport\Http\Middleware\CheckTokenForAnyScope::class,
            'mcp.tokens' => \App\Http\Middleware\EnsureCanManageApiTokens::class,
            'mcp.consent' => \App\Http\Middleware\EnsureMcpConsentAllowed::class,
            'mcp.ip-throttle' => \App\Http\Middleware\ThrottleMcpByIp::class,
            'mcp.audit' => \App\Http\Middleware\RecordMcpToolCall::class,
            'deny-framing' => \App\Http\Middleware\DenyFraming::class,
        ]);

        // "Sign in with Apple" POSTs its callback cross-site from apple.com (form_post
        // response mode), so it can't carry a Laravel CSRF token — exempt just that route.
        $middleware->validateCsrfTokens(except: [
            'auth/apple/callback',
        ]);

        // Configure maintenance mode to only block creation routes
        // This allows the frontend to remain accessible
        $middleware->preventRequestsDuringMaintenance(except: [
            // Frontend-related paths (with wildcard patterns)
            '/',
            'index/search', // No leading slash for pattern matching
            'events/*', // Use wildcard without leading slash
            'organizers/*', // Use wildcard without leading slash
            'communities', // Base communities page
            'communities/*/posts/*', // Allow viewing community posts, but not editing
            'communities/*', // Allow viewing community profiles, but not editing
            'terms',
            'privacy',
            'sitemap',
            'sitemap.xml',
            // Add any API routes needed for frontend functionality
            'api/index/search',
            'api/events/*',
            'api/communities/*/posts/*', // API endpoints for viewing communities
            'api/communities/*', // API endpoints for viewing communities
            // Allow assets
            'storage/*',
            'assets/*',
            // Exclude authentication paths
            'login',
            'register',
            'logout',
            'password/*',
            // Allow admin users to bypass
            'admin/*',
            'api/admin/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        \Sentry\Laravel\Integration::handles($exceptions);

        // A bearer token Passport cannot accept on /mcp (expired, revoked,
        // garbage, an old pre-OAuth key) is a 401 by design, but Passport's
        // guard report()s the refusal before answering. Left alone that is a
        // Sentry event per stale token — every assistant, every hour. The
        // audit table keeps the 401s; Sentry keeps the real errors.
        $exceptions->dontReport(\League\OAuth2\Server\Exception\OAuthServerException::class);
    })
    ->withCommands([
        CheckClosingEvents::class,
        ArchiveOldClicks::class,
    ])
    ->create();
