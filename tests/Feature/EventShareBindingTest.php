<?php

/**
 * The share button's handler lives on `window`, assigned by an inline script
 * in show.blade.php, while the button itself is a Vue component emitting
 * `share`. That makes every `@share` binding a Vue template expression, not a
 * DOM onclick — it compiles to a `with(_ctx)` lookup that only reaches a
 * global by falling through the component proxy.
 *
 * A bare `handleShare()` there has reported "handleShare is not a function"
 * from production twice: Sentry EI-VUE-12, and EI-VUE-15 again after the
 * first fix moved the assignment out of DOMContentLoaded. Rather than guess
 * at a third timing fix, the bindings name `window` explicitly and call
 * optionally, so a missing handler is a no-op rather than a thrown error.
 *
 * This is the guard on that, because the failure only shows up in production
 * on a real tap and there is nothing else asserting the binding's shape.
 */
function shareBindingSources(): array
{
    return [
        'events/show.blade.php' => file_get_contents(resource_path('views/events/show.blade.php')),
        'events/show/header-mobile.blade.php' => file_get_contents(resource_path('views/events/show/header-mobile.blade.php')),
    ];
}

test('every @share binding calls through window explicitly', function () {
    foreach (shareBindingSources() as $file => $source) {
        preg_match_all('/@share="([^"]+)"/', $source, $matches);

        expect($matches[1])->not->toBeEmpty("no @share binding found in {$file} — was it renamed?");

        foreach ($matches[1] as $expression) {
            // str_contains + toBeTrue, not toContain: toContain takes needles,
            // and a message passed as its second argument silently becomes
            // another thing it searches for (same trap noted in
            // CurrencyCatalogTest).
            expect(str_starts_with($expression, 'window.'))
                ->toBeTrue("{$file}: `{$expression}` relies on Vue's scope fallthrough to reach a global");

            expect(str_contains($expression, '?.('))
                ->toBeTrue("{$file}: `{$expression}` would throw rather than no-op if the handler is missing");
        }
    }
});

test('the handler the bindings call is defined on both sides of the mobile split', function () {
    // handleShare is only assigned inside the isMobile branch; toggleShareModal
    // is assigned in both, so the binding cannot depend on which branch ran.
    $source = shareBindingSources()['events/show.blade.php'];

    expect(substr_count($source, 'window.toggleShareModal = function'))->toBe(2);
});
