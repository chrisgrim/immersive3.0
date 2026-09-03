<?php

use App\Models\Event;
use App\Models\Events\Show;
use App\Models\Events\Ticket;
use App\Models\Organizer;
use App\Models\User;
use App\Support\Currency;
use App\Support\Validation\EventUpdateRules;
use Illuminate\Support\Facades\DB;

/**
 * Ticket currencies are ISO 4217 codes rendered by ICU (App\Support\Currency).
 *
 * Until 2026-09-02 the column held a display symbol from a hand-maintained
 * list, copied into the validator, the wizard picker, a zero-decimal list in
 * six Vue files, and the event page's JSON-LD — this file existed to keep
 * those copies agreeing. There is no list to agree on now: PHP and the
 * browser both read resources/data/currencies.json and both format through
 * their platform's CLDR data with the same 'en' locale. What these tests pin
 * is that contract — the outputs both sides are expected to produce.
 */
function ticketsVue(): string
{
    return file_get_contents(resource_path('js/PageComponents/Creation/Core/Pages/tickets.vue'));
}

function validateTickets(array $tier): \Illuminate\Validation\Validator
{
    return validator(
        ['tickets' => [$tier + ['name' => 'General', 'description' => '']]],
        EventUpdateRules::rules(),
        EventUpdateRules::messages(),
    );
}

// ── the code list ────────────────────────────────────────────────────────

test('the code list is a set of current ISO 4217 codes', function () {
    $codes = Currency::codes();

    expect(count($codes))->toBeGreaterThan(150);
    expect($codes)->toBe(array_values(array_unique($codes)));

    foreach ($codes as $code) {
        expect($code)->toMatch('/^[A-Z]{3}$/');
    }

    // Every currency the old symbol list covered, plus the ones that were
    // being requested one email at a time.
    foreach (['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'MXN', 'CNY', 'KRW', 'AUD', 'HKD', 'TWD', 'THB', 'SGD', 'INR'] as $code) {
        expect(Currency::isValid($code))->toBeTrue("{$code} missing from the code list");
    }
});

test('retired currencies ICU still lists are left out', function () {
    // Nobody prices a ticket in the pre-2024 Zimbabwean dollar or the kuna;
    // offering them in the picker only invites a wrong choice. Pruned by
    // scripts/generate-currency-list.mjs.
    foreach (['ZWL', 'HRK', 'SLL', 'ANG', 'CUC', 'XDR', 'XSU'] as $retired) {
        expect(Currency::isValid($retired))->toBeFalse("{$retired} should be retired");
    }
    // Their replacements are in.
    foreach (['ZWG', 'SLE', 'XCG'] as $current) {
        expect(Currency::isValid($current))->toBeTrue("{$current} missing");
    }
});

test('isValid is exact: no symbols, no lower case, no codes ICU does not know', function () {
    foreach (['$', 'usd', 'US$', 'BTC', 'XXX', '', null, 17] as $bad) {
        expect(Currency::isValid($bad))->toBeFalse(json_encode($bad).' should not validate');
    }
});

// ── formatting ───────────────────────────────────────────────────────────

test('format reproduces the conventions the old symbol list encoded by hand', function () {
    // Bare "$" is USD; the other dollars get a disambiguating prefix; the two
    // yen-glyph currencies are told apart; JPY and KRW carry no decimals.
    expect(Currency::format(17.5, 'USD'))->toBe('$17.50');
    expect(Currency::format(25, 'AUD'))->toBe('A$25.00');
    expect(Currency::format(25, 'CAD'))->toBe('CA$25.00');
    expect(Currency::format(25, 'HKD'))->toBe('HK$25.00');
    expect(Currency::format(25, 'TWD'))->toBe('NT$25.00');
    expect(Currency::format(25, 'MXN'))->toBe('MX$25.00');
    expect(Currency::format(99.5, 'CNY'))->toBe('CN¥99.50');
    expect(Currency::format(1500, 'JPY'))->toBe('¥1,500');
    expect(Currency::format(144000, 'KRW'))->toBe('₩144,000');
    expect(Currency::format(0.99, 'EUR'))->toBe('€0.99');
    expect(Currency::format(10, 'GBP'))->toBe('£10.00');
    expect(Currency::format(500, 'INR'))->toBe('₹500.00');
});

test('a currency with no well-known symbol is written as code and space', function () {
    // No one has to invent a glyph for these; the code is the unambiguous form.
    expect(Currency::format(45, 'SGD'))->toBe('SGD 45.00');
    expect(Currency::format(1200, 'THB'))->toBe('THB 1,200.00');
});

test('compact formatting drops zero decimals from a whole amount only', function () {
    expect(Currency::format(40, 'USD', compact: true))->toBe('$40');
    expect(Currency::format(17.5, 'USD', compact: true))->toBe('$17.50');
    expect(Currency::format(45, 'SGD', compact: true))->toBe('SGD 45');
    expect(Currency::format(144000, 'KRW', compact: true))->toBe('₩144,000');
});

test('an unknown or missing code formats as the default currency rather than erroring', function () {
    expect(Currency::format(10, null))->toBe('$10.00');
    expect(Currency::format(10, 'BTC'))->toBe('$10.00');
});

test('a legacy stored symbol formats as the currency it meant', function () {
    // On deploy the new code is live for the seconds before the migration
    // has converted every row; a '£' ticket must not render as dollars then.
    expect(Currency::format(17.5, '£'))->toBe('£17.50');
    expect(Currency::format(25, 'AU'))->toBe('A$25.00');
    expect(Currency::decimals('₩'))->toBe(0);
    expect(Currency::symbol('C$'))->toBe('CA$');
    expect(Ticket::getPriceRange([25, 80], '£'))->toBe('£25 - £80');
});

test('decimals come from CLDR, not a list', function () {
    expect(Currency::decimals('USD'))->toBe(2);
    expect(Currency::decimals('JPY'))->toBe(0);
    expect(Currency::decimals('KRW'))->toBe(0);
    // The yuan subdivides into 100 fen; the old hand list once got this wrong.
    expect(Currency::decimals('CNY'))->toBe(2);
    expect(Currency::decimals('TWD'))->toBe(2);
    // KWD is written with three per CLDR, but the column holds two: capped.
    expect(Currency::decimals('KWD'))->toBe(2);
    expect(Currency::decimals(null))->toBe(2);
});

test('a three-decimal currency is entered, shown and validated with two', function () {
    // tickets.ticket_price is decimal(8,2); MySQL would round "1.234" to
    // 1.23 silently on the way in (and SQLite in tests would keep it, hiding
    // the loss). So the third decimal is refused up front, and formatting
    // never shows one.
    expect(Currency::format(1.23, 'KWD'))->toBe('KWD 1.23');
    expect(validateTickets(['ticket_price' => 1.23, 'currency' => 'KWD'])->passes())->toBeTrue();
    expect(validateTickets(['ticket_price' => 1.234, 'currency' => 'KWD'])->passes())->toBeFalse();
    expect(validateTickets(['ticket_price' => 17.505, 'currency' => 'USD'])->passes())->toBeFalse();
    expect(validateTickets(['ticket_price' => 17.505, 'currency' => 'USD'])->errors()->first('tickets.0.ticket_price'))
        ->toContain('decimal places');
});

test('symbol is the prefix ICU prints, falling back to the code itself', function () {
    expect(Currency::symbol('USD'))->toBe('$');
    expect(Currency::symbol('AUD'))->toBe('A$');
    expect(Currency::symbol('INR'))->toBe('₹');
    expect(Currency::symbol('SGD'))->toBe('SGD');
    // A phrase, with a plain space — ICU's own has a narrow non-breaking one.
    expect(Currency::symbol('XOF'))->toBe('F CFA');
});

// ── normalising what clients send ────────────────────────────────────────

test('normalize maps the old stored symbols and habitual variants to the code', function () {
    expect(Currency::normalize('$'))->toBe('USD');
    expect(Currency::normalize(' usd '))->toBe('USD');
    expect(Currency::normalize('A$'))->toBe('AUD');
    // The literal four published Australian events stored for years.
    expect(Currency::normalize('AU'))->toBe('AUD');
    expect(Currency::normalize('₩'))->toBe('KRW');
    expect(Currency::normalize('CN¥'))->toBe('CNY');
    expect(Currency::normalize('sgd'))->toBe('SGD');
});

test('normalize leaves an unrecognised value alone so validation can name it', function () {
    expect(Currency::normalize('BTC'))->toBe('BTC');
    expect(Currency::normalize(null))->toBeNull();
    expect(Currency::normalize(17))->toBe(17);
});

// ── the location default ─────────────────────────────────────────────────

test('forCountry maps an ISO country code to its currency', function () {
    expect(Currency::forCountry('SG'))->toBe('SGD');
    expect(Currency::forCountry('au'))->toBe('AUD');
    expect(Currency::forCountry('GB'))->toBe('GBP');
    expect(Currency::forCountry('IN'))->toBe('INR');
    expect(Currency::forCountry('TW'))->toBe('TWD');
    // Bulgaria joined the euro on 2026-01-01.
    expect(Currency::forCountry('BG'))->toBe('EUR');
});

test('forCountry accepts the English names a few hundred older location rows hold', function () {
    expect(Currency::forCountry('United States'))->toBe('USD');
    expect(Currency::forCountry('United Kingdom'))->toBe('GBP');
});

test('forCountry gives no default when it has nothing to go on', function () {
    expect(Currency::forCountry(null))->toBeNull();
    expect(Currency::forCountry(''))->toBeNull();
    expect(Currency::forCountry('ZZ'))->toBeNull();
    expect(Currency::forCountry('Narnia'))->toBeNull();
});

test('every country in the map points at a currency the validator accepts', function () {
    $map = json_decode(file_get_contents(resource_path('data/country-currency.json')), true);

    expect(count($map))->toBeGreaterThan(240);

    foreach ($map as $country => $code) {
        expect($country)->toMatch('/^[A-Z]{2}$/');
        expect(Currency::isValid($code))->toBeTrue("{$country} maps to unknown currency {$code}");
    }
});

// ── validation ───────────────────────────────────────────────────────────

test('the validator accepts any current code and rejects symbols', function () {
    foreach (['USD', 'SGD', 'KRW', 'ZAR'] as $code) {
        expect(validateTickets(['ticket_price' => 20, 'currency' => $code])->passes())->toBeTrue("{$code} rejected");
    }

    foreach (['$', 'A$', 'usd', 'BTC'] as $bad) {
        $validator = validateTickets(['ticket_price' => 17.5, 'currency' => $bad]);
        expect($validator->errors()->has('tickets.0.currency'))->toBeTrue("{$bad} accepted");
    }
});

test('the currency error says what shape is wanted instead of listing 160 codes', function () {
    $message = validateTickets(['ticket_price' => 17.5, 'currency' => '$'])->errors()->first('tickets.0.currency');

    expect($message)->toContain('ISO 4217');
    expect(strlen($message))->toBeLessThan(200);
});

// ── the price ceiling (unchanged contract, still copied into the wizard) ──

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
    // JPY/KRW have no minor unit, so their everyday prices are 4-6 digits.
    expect(EventUpdateRules::MAX_TICKET_PRICE)->toBeGreaterThan(144000.0);
});

test('the ceiling fits the column that stores it', function () {
    // tickets.ticket_price is decimal(8,2) — 999999.99 is its largest value.
    expect(EventUpdateRules::MAX_TICKET_PRICE)->toBeLessThanOrEqual(999999.99);
});

test('a ticket priced in a zero-decimal currency validates', function () {
    $passes = fn (float $price) => validateTickets(['ticket_price' => $price, 'currency' => 'KRW'])->passes();

    expect($passes(144000))->toBeTrue('144,000 KRW is an ordinary ticket price');
    // The ceiling as a WHOLE number — the .99 form of it is rejected in a
    // zero-decimal currency by ZeroDecimalPriceRule, which is the point.
    expect($passes(999999))->toBeTrue();
    expect($passes(1000000))->toBeFalse('past the column ceiling, so still rejected');
});

test('the ceiling itself is reachable in a currency that has decimals', function () {
    $passes = fn (float $price) => validateTickets(['ticket_price' => $price, 'currency' => 'USD'])->passes();

    expect($passes(EventUpdateRules::MAX_TICKET_PRICE))->toBeTrue();
    expect($passes(1000000))->toBeFalse();
});

test('the price cap explains itself rather than failing bare', function () {
    expect(EventUpdateRules::messages()['tickets.*.ticket_price.max'])
        ->toContain(number_format(EventUpdateRules::MAX_TICKET_PRICE, 2));
});

// ── zero-decimal currencies ──────────────────────────────────────────────

test('a currency with no minor unit rejects a fractional price', function () {
    // Otherwise 144000.50 in KRW is stored happily, then shown as "144000.5"
    // in the editor and "₩144,001" on the live listing — a page quoting a
    // price the database does not hold.
    expect(validateTickets(['ticket_price' => 144000.50, 'currency' => 'KRW'])->passes())->toBeFalse();
    expect(validateTickets(['ticket_price' => 1500.25, 'currency' => 'JPY'])->passes())->toBeFalse();

    // Whole numbers are fine, and currencies WITH a minor unit are untouched
    // — CNY among them: the yuan subdivides into 100 fen.
    expect(validateTickets(['ticket_price' => 144000, 'currency' => 'KRW'])->passes())->toBeTrue();
    expect(validateTickets(['ticket_price' => 17.50, 'currency' => 'USD'])->passes())->toBeTrue();
    expect(validateTickets(['ticket_price' => 0.99, 'currency' => 'EUR'])->passes())->toBeTrue();
    expect(validateTickets(['ticket_price' => 99.99, 'currency' => 'CNY'])->passes())->toBeTrue();
});

test('a PWYC tier keeps its sentinel price in a zero-decimal currency', function () {
    // The wizard writes 0.01 for PWYC to mean "not free, pay what you can",
    // hides the price input, and every price surface prints "PWYC" in its
    // place (Ticket::getPriceRange, show-purchase.vue). Rejecting the sentinel
    // as a fractional KRW price made a PWYC tier unsaveable in KRW/JPY — with
    // no field on screen to fix it from.
    expect(validateTickets(['name' => 'PWYC', 'ticket_price' => 0.01, 'currency' => 'KRW'])->passes())->toBeTrue();
    // The same case-insensitive, trimmed test the display code applies.
    expect(validateTickets(['name' => ' pwyc ', 'ticket_price' => 0.01, 'currency' => 'KRW'])->passes())->toBeTrue();
    // Any other tier is still held to whole numbers.
    expect(validateTickets(['name' => 'General', 'ticket_price' => 0.01, 'currency' => 'KRW'])->passes())->toBeFalse();
});

// ── the precomputed price_range string ───────────────────────────────────

test('price_range strings keep their compact shape, now in any currency', function () {
    expect(Ticket::getPriceRange([40], 'USD'))->toBe('$40');
    expect(Ticket::getPriceRange([17.5], 'USD'))->toBe('$17.50');
    expect(Ticket::getPriceRange([80, 25], 'USD'))->toBe('$25 - $80');
    expect(Ticket::getPriceRange([0], 'USD'))->toBe('Free');
    expect(Ticket::getPriceRange([0, 30], 'USD'))->toBe('Free - $30');
    expect(Ticket::getPriceRange([0.01, 80], 'USD', ['PWYC', 'VIP']))->toBe('PWYC - $80');
    expect(Ticket::getPriceRange([25, 80], 'AUD'))->toBe('A$25 - A$80');
    expect(Ticket::getPriceRange([45], 'SGD'))->toBe('SGD 45');
    expect(Ticket::getPriceRange([144000], 'KRW'))->toBe('₩144,000');
});

test('a stored value the migration could not map keeps the old verbatim form', function () {
    // Rather than being silently rendered as dollars. (A legacy SYMBOL is
    // mapped, not verbatim — see the test above.)
    expect(Ticket::getPriceRange([25], 'XX'))->toBe('XX25');
});

test('the event page formats through Currency rather than its own list', function () {
    $blade = file_get_contents(resource_path('views/events/show.blade.php'));

    expect($blade)
        ->toContain('\App\Support\Currency::format(')
        ->not->toContain('decimalsFor')
        ->not->toContain("['¥', 'CN¥', '₩']");
});

// ── the browser side ─────────────────────────────────────────────────────

test('the Vue side reads the same two data files rather than its own list', function () {
    $composable = file_get_contents(resource_path('js/composables/useCurrency.js'));

    expect($composable)
        ->toContain("from '../../data/currencies.json'")
        ->toContain("from '../../data/country-currency.json'")
        ->toContain("const LOCALE = '".Currency::LOCALE."'");
});

test('no Vue file keeps a private copy of the zero-decimal list any more', function () {
    // Six of these existed; each was a chance for one surface to print
    // "₩144000.00" while the rest printed "₩144,000".
    $files = [
        'js/PageComponents/Creation/Core/Pages/tickets.vue',
        'js/PageComponents/Creation/Core/Pages/navSidebar.vue',
        'js/PageComponents/Creation/Core/Pages/review.vue',
        'js/PageComponents/EventShow/show-purchase.vue',
        'js/PageComponents/EventShow/show-purchase-mobile.vue',
        'js/PageComponents/Admin/Approval/EventReview.vue',
    ];

    foreach ($files as $file) {
        $source = file_get_contents(resource_path($file));

        // toContain() takes needles, not a message — a second argument
        // silently becomes another thing it searches for.
        expect(str_contains($source, 'ZERO_DECIMAL_CURRENCIES'))->toBeFalse("{$file} still carries its own list");
        expect(str_contains($source, '@/composables/useCurrency'))->toBeTrue("{$file} does not use the shared formatter");
    }
});

// ── the migration ────────────────────────────────────────────────────────

test('the ISO migration maps stored symbols, repairs "AU", and rebuilds price_range', function () {
    $event = Event::factory()->create([
        'organizer_id' => Organizer::factory()->create()->id,
        'user_id' => User::factory()->create()->id,
    ]);
    $show = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(3)->format('Y-m-d H:i:s')]);
    $show->tickets()->create(['name' => 'General', 'ticket_price' => 25, 'currency' => 'AU', 'type' => 's']);
    $show->tickets()->create(['name' => 'VIP', 'ticket_price' => 80, 'currency' => 'AU', 'type' => 's']);
    DB::table('events')->where('id', $event->id)->update(['price_range' => 'AU25 - AU80']);

    $other = Event::factory()->create([
        'organizer_id' => Organizer::factory()->create()->id,
        'user_id' => User::factory()->create()->id,
    ]);
    $otherShow = Show::factory()->create(['event_id' => $other->id, 'date' => now()->addDays(3)->format('Y-m-d H:i:s')]);
    $otherShow->tickets()->create(['name' => 'General', 'ticket_price' => 144000, 'currency' => '₩', 'type' => 's']);
    $otherShow->tickets()->create(['name' => 'Odd', 'ticket_price' => 5, 'currency' => 'XX', 'type' => 's']);

    $migration = include database_path('migrations/2026_09_02_180000_convert_ticket_currencies_to_iso_codes.php');
    $migration->up();

    expect($show->tickets()->pluck('currency')->unique()->values()->all())->toBe(['AUD']);
    expect($event->fresh()->price_range)->toBe('A$25 - A$80');

    expect($otherShow->tickets()->where('name', 'General')->value('currency'))->toBe('KRW');
    // Unknown values are reported, not rewritten.
    expect($otherShow->tickets()->where('name', 'Odd')->value('currency'))->toBe('XX');
});
