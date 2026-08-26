<?php

use App\Support\Validation\EventUpdateRules;

/**
 * The currency catalog used to be maintained by hand in four places: the
 * validation allowlist, the wizard picker's symbol list, that picker's
 * ISO labels, and an elseif ladder in the event page's JSON-LD. They all
 * happened to agree, but nothing checked it, so adding a currency to three
 * of the four would have shipped silently.
 *
 * EventUpdateRules::CURRENCY_ISO is now the single source. The blade reads
 * it directly. A Vue constant can't import a PHP one, so these assert the
 * wizard's copy against it — the drift has to fail here before it can reach
 * a user picking a currency the backend then rejects.
 */
function ticketsVue(): string
{
    return file_get_contents(resource_path('js/PageComponents/Creation/Core/Pages/tickets.vue'));
}

test('CURRENCIES is exactly the keys of CURRENCY_ISO', function () {
    expect(EventUpdateRules::CURRENCIES)->toBe(array_keys(EventUpdateRules::CURRENCY_ISO));
});

test('every currency maps to a 3-letter ISO 4217 code', function () {
    foreach (EventUpdateRules::CURRENCY_ISO as $symbol => $iso) {
        expect($iso)->toMatch('/^[A-Z]{3}$/', "'{$symbol}' maps to an invalid ISO code");
    }
});

test('the wizard picker offers exactly the currencies the backend accepts', function () {
    preg_match('/const CURRENCY_SYMBOLS = \[(.*?)\];/s', ticketsVue(), $m);
    expect($m)->not->toBeEmpty('CURRENCY_SYMBOLS not found in tickets.vue — was it renamed?');

    preg_match_all("/'([^']+)'/", $m[1], $symbols);

    expect($symbols[1])->toBe(EventUpdateRules::CURRENCIES);
});

test('the wizard picker labels every currency with the same ISO code the backend uses', function () {
    preg_match('/const CURRENCY_LABELS = \{(.*?)\};/s', ticketsVue(), $m);
    expect($m)->not->toBeEmpty('CURRENCY_LABELS not found in tickets.vue — was it renamed?');

    preg_match_all("/'([^']+)':\s*'([^']+)'/", $m[1], $pairs, PREG_SET_ORDER);

    $labels = collect($pairs)->mapWithKeys(fn ($p) => [$p[1] => $p[2]]);

    expect($labels->keys()->all())->toBe(EventUpdateRules::CURRENCIES);

    foreach (EventUpdateRules::CURRENCY_ISO as $symbol => $iso) {
        // e.g. '$ — USD' / 'MX$ — MXN (Mexican peso)' — the label is free to
        // add a clarifier, but the ISO code in it has to be the real one.
        expect($labels[$symbol])->toContain($iso);
    }
});

test('every alias resolves to a currency that actually exists', function () {
    foreach (EventUpdateRules::CURRENCY_ALIASES as $alias => $symbol) {
        // toContain() takes needles, not a message — a message passed as a
        // second argument silently becomes another thing it searches for.
        expect(in_array($symbol, EventUpdateRules::CURRENCIES, true))
            ->toBeTrue("alias '{$alias}' points at unknown symbol '{$symbol}'");
    }
});
