<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Console\Commands\CheckClosingEvents;

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

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'moderator' => \App\Http\Middleware\ModeratorMiddleware::class,
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
            'privacy-choices',
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
        //
    })
    ->withCommands([
        CheckClosingEvents::class,
    ])
    ->create();
