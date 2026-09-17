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

/**
 * The share modals' own buttons are plain `onclick="name()"` attributes, so
 * each name has to be assigned on window by an inline script in the SAME
 * rendered page. Desktop only got toggleShareModal; its "Copy Link" button
 * threw "copyLink is not defined" (Sentry EI-VUE-16) the moment desktop
 * Share started opening again. Render both sides of the isMobile split and
 * check every inline handler against the page it shipped with.
 */
function shareModalPage(?string $userAgent): string
{
    $organizer = \App\Models\Organizer::factory()->create(['status' => 'p']);
    $event = \App\Models\Event::factory()->published()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->addDays(30),
        'hasLocation' => true,
    ]);
    \App\Models\Events\Location::factory()->create(['event_id' => $event->id]);
    \App\Models\Events\Show::factory()->create(['event_id' => $event->id]);
    $event->priceranges()->create(['price' => '25']);
    $event->advisories()->create(['wheelchairReady' => true]);

    $request = $userAgent ? test()->withHeader('User-Agent', $userAgent) : test();

    return $request->get("/events/{$event->slug}")->assertOk()->getContent();
}

test('every inline onclick handler on the event page is defined by that same page', function (?string $userAgent) {
    $html = shareModalPage($userAgent);

    // Tripwire: the mobile dataset must really render the mobile branch, or
    // it silently becomes a second copy of the desktop run.
    expect(str_contains($html, 'window.showPhotoGallery = function'))->toBe($userAgent !== null);

    preg_match_all('/onclick="([^"]+)"/', $html, $attributes);
    expect($attributes[1])->not->toBeEmpty();

    $called = collect($attributes[1])
        ->flatMap(function (string $expression) {
            // Bare `name()` calls only: `window.history.back()` is a method.
            preg_match_all('/(?<![.\w])([A-Za-z_]\w*)\(\)/', $expression, $names);

            return $names[1];
        })
        ->unique()
        ->values();

    expect($called)->toContain('copyLink');

    foreach ($called as $name) {
        expect(str_contains($html, "window.{$name} = function"))
            ->toBeTrue("onclick=\"{$name}()\" is rendered but window.{$name} is never assigned on this page");
    }
})->with([
    'desktop' => [null],
    'mobile' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'],
]);
