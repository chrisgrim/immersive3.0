<?php

/**
 * The Share button's handler lives on `window`, assigned by an inline script
 * in show.blade.php, while the button itself is a Vue component emitting
 * `share` from inside Blade. That makes every `@share` binding a runtime-
 * compiled Vue template expression, evaluated inside `with(_ctx)` — and Vue's
 * runtime proxy claims every identifier that is not on its short globals
 * allowlist, `window` included. So a binding can reach the handler ONLY
 * through something registered on app.config.globalProperties, which is what
 * resources/js/bladeBridge.js does. (Sentry EI-VUE-12 and EI-VUE-15 were this,
 * not the DOMContentLoaded race two earlier fixes assumed.)
 *
 * tests/js/blade-bridge.spec.js proves the mechanism by mounting the real
 * bindings and clicking. This is the cheap PHP-side tripwire on the shape:
 * every binding must call the name the bridge registers, and nothing else.
 */
function shareBindingSources(): array
{
    return [
        'events/show.blade.php' => file_get_contents(resource_path('views/events/show.blade.php')),
        'events/show/header-mobile.blade.php' => file_get_contents(resource_path('views/events/show/header-mobile.blade.php')),
    ];
}

test('every @share binding calls the handler the Blade bridge registers', function () {
    $bridge = file_get_contents(resource_path('js/bladeBridge.js'));
    expect(str_contains($bridge, 'globalProperties.toggleShareModal'))->toBeTrue();

    foreach (shareBindingSources() as $file => $source) {
        preg_match_all('/@share="([^"]+)"/', $source, $matches);

        expect($matches[1])->not->toBeEmpty("no @share binding found in {$file} — was it renamed?");

        foreach ($matches[1] as $expression) {
            // str_contains + toBeTrue, not toContain: toContain takes needles,
            // and a message passed as its second argument silently becomes
            // another thing it searches for (same trap noted in
            // CurrencyCatalogTest).
            expect($expression === 'toggleShareModal()')
                ->toBeTrue("{$file}: `{$expression}` is not the bridged handler — anything else resolves through Vue's proxy to undefined");
        }
    }
});

test('app.js installs the bridge', function () {
    $app = file_get_contents(resource_path('js/app.js'));

    expect(str_contains($app, 'installBladeBridge(app)'))->toBeTrue();
});

test('the handler the bridge calls is defined on both sides of the mobile split', function () {
    // handleShare is only assigned inside the isMobile branch; toggleShareModal
    // is assigned in both, so the bridge cannot depend on which branch ran.
    $source = shareBindingSources()['events/show.blade.php'];

    expect(substr_count($source, 'window.toggleShareModal = function'))->toBe(2);
});
