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

/**
 * The price ceiling is part of the currency contract, not separate from it:
 * 99999.99 is a reasonable cap in dollars and an absurd one in won. A normal
 * ticket to Sleep No More Seoul is 144,000 KRW, and both the validator and the
 * wizard rejected it — the wizard by silently truncating it to 14,400.
 */
test('the wizard enforces exactly the price ceiling the backend accepts', function () {
    preg_match('/const MAX_TICKET_PRICE = ([\d.]+);/', ticketsVue(), $m);
    expect($m)->not->toBeEmpty('MAX_TICKET_PRICE not found in tickets.vue — was it renamed?');

    expect((float) $m[1])->toBe(EventUpdateRules::MAX_TICKET_PRICE);
});

test('the wizard derives its digit cap so it can never truncate an accepted price', function () {
    // The regression this guards: a literal `parts[0].length > 5` silently
    // rewrote 144000 as 14400 while typing — a wrong price, saved without an
    // error, which is worse than the rejection it accompanied.
    expect(ticketsVue())->toContain('const MAX_PRICE_DIGITS = String(Math.floor(MAX_TICKET_PRICE)).length;');
});

test('the ceiling clears ordinary prices in the zero-decimal currencies', function () {
    // ¥/CN¥/₩ have no minor unit, so their everyday prices are 4-6 digits.
    expect(EventUpdateRules::MAX_TICKET_PRICE)->toBeGreaterThan(144000.0);
});

test('the ceiling fits the column that stores it', function () {
    // tickets.ticket_price is decimal(8,2) — 999999.99 is its largest value.
    // Raising the rule past this would trade a validation error for a
    // truncated or out-of-range write at the database.
    expect(EventUpdateRules::MAX_TICKET_PRICE)->toBeLessThanOrEqual(999999.99);
});

test('a ticket priced in a zero-decimal currency validates', function () {
    $validate = fn (float $price) => validator(
        ['tickets' => [['name' => 'General', 'ticket_price' => $price, 'currency' => '₩', 'description' => '']]],
        EventUpdateRules::rules(),
        EventUpdateRules::messages(),
    )->passes();

    expect($validate(144000))->toBeTrue('144,000 KRW is an ordinary ticket price');
    expect($validate(EventUpdateRules::MAX_TICKET_PRICE))->toBeTrue();
    expect($validate(1000000))->toBeFalse('past the column ceiling, so still rejected');
});

test('the price cap explains itself rather than failing bare', function () {
    expect(EventUpdateRules::messages()['tickets.*.ticket_price.max'])
        ->toContain(number_format(EventUpdateRules::MAX_TICKET_PRICE, 2));
});
