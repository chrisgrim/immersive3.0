<?php

/*
 * A numeric throttle ("throttle:20,1") counts on one key per user (or per IP),
 * shared by every other numeric throttle on the site: browsing used up the
 * Duplicate limit. Each one names its own counter as the third argument.
 */
test('every numeric rate limit has its own counter', function () {
    $shared = collect(app('router')->getRoutes()->getRoutes())
        ->flatMap(fn ($route) => collect($route->gatherMiddleware())->map(fn ($middleware) => [$route->uri(), $middleware]))
        ->filter(fn ($pair) => is_string($pair[1]) && preg_match('/^throttle:\d+,\d+$/', $pair[1]))
        ->map(fn ($pair) => implode(' ', $pair))
        ->values()
        ->all();

    expect($shared)->toBe([]);
});
