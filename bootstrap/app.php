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
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'mcp.tokens' => \App\Http\Middleware\EnsureCanManageApiTokens::class,
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
    })
    ->withCommands([
        CheckClosingEvents::class,
        ArchiveOldClicks::class,
    ])
    ->create();
