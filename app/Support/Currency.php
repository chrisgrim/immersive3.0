<?php

namespace App\Support;

use Illuminate\Support\Number;
use NumberFormatter;

/**
 * Ticket currencies: stored as ISO 4217 codes, rendered by ICU.
 *
 * Before 2026-09-02 the `tickets.currency` column held a display symbol
 * ('$', 'C$', 'MX$', '₩'…) chosen from a hand-maintained list, and every
 * price surface concatenated it onto the number itself. Each new country
 * meant inventing a symbol, deciding its decimals by hand, and editing
 * seven copies of the list — and four published Australian events had
 * been storing the literal "AU" since 2020, rendering "AU25.00", because
 * no symbol existed for them.
 *
 * ICU already ships the answer to all of that. With the 'en' locale it
 * renders the same conventions the list encoded by hand (A$, HK$, NT$,
 * MX$, CN¥; ¥ and ₩ without decimals) and, for currencies without a
 * well-known Latin symbol, the unambiguous code-plus-space form
 * ("SGD 45.00", "THB 1,200.00"). Decimal places come from CLDR, so there
 * is no zero-decimal list to keep in step. The browser has the same data
 * (Intl.NumberFormat / Intl.DisplayNames), so the wizard and the live page
 * agree without sharing anything but the code — see
 * resources/js/composables/useCurrency.js.
 *
 * The code list and the country map live in resources/data/ as JSON so the
 * PHP and Vue sides read one file rather than two hand-synced copies. The
 * code list is generated (node scripts/generate-currency-list.mjs) from ICU
 * minus a few retired codes ICU still carries.
 */
final class Currency
{
    /**
     * Every price on the site is formatted in this one locale, whatever
     * the viewer's browser is set to — it is what makes bare "$" mean USD
     * and gives the other dollars their prefixes.
     */
    public const LOCALE = 'en';

    public const DEFAULT = 'USD';

    /**
     * The most decimal places a stored price can carry: tickets.ticket_price
     * is decimal(8,2). CLDR writes KWD, BHD, JOD, OMR, TND, LYD and IQD with
     * three, and MySQL would silently round a third away on the way in — so
     * those are entered, validated (decimal:0,2) and shown with two here.
     */
    public const MAX_DECIMALS = 2;

    private const CODES_FILE = 'data/currencies.json';

    private const COUNTRY_FILE = 'data/country-currency.json';

    /**
     * Values a client is likely to send instead of the code: the symbols the
     * column held before the ISO migration, and a few habitual variants.
     * Normalising an unambiguous one beats rejecting it and making the
     * caller guess. Keys are compared after trim + upper-case.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        '$' => 'USD', 'US$' => 'USD',
        '€' => 'EUR',
        '£' => 'GBP',
        '¥' => 'JPY',
        'C$' => 'CAD', 'CA$' => 'CAD',
        'A$' => 'AUD', 'AU$' => 'AUD', 'AU' => 'AUD',
        'HK$' => 'HKD',
        'NT$' => 'TWD', 'NTD' => 'TWD',
        'S$' => 'SGD',
        'NZ$' => 'NZD',
        'MX$' => 'MXN',
        'CN¥' => 'CNY', 'RMB' => 'CNY',
        '₩' => 'KRW',
        '฿' => 'THB',
        '₹' => 'INR',
        'R$' => 'BRL',
    ];

    /**
     * The wizard has always written the ISO country code, but a few hundred
     * older location rows hold the English name instead. Enough to cover
     * those; anything else unrecognised simply gets no default.
     *
     * @var array<string, string>
     */
    private const COUNTRY_NAMES = [
        'UNITED STATES' => 'US',
        'CANADA' => 'CA',
        'UNITED KINGDOM' => 'GB',
        'AUSTRALIA' => 'AU',
    ];

    /** @var array<int, string>|null */
    private static ?array $codes = null;

    /** @var array<string, string>|null */
    private static ?array $countries = null;

    private static ?NumberFormatter $formatter = null;

    /**
     * Every accepted code — ICU's list of current currencies.
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return self::$codes ??= self::readJson(self::CODES_FILE);
    }

    public static function isValid(mixed $code): bool
    {
        return is_string($code) && in_array($code, self::codes(), true);
    }

    /**
     * Resolve a caller-supplied value to a stored code, or return it
     * unchanged so validation can reject it with the right message.
     */
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $key = mb_strtoupper(trim($value));

        if (self::isValid($key)) {
            return $key;
        }

        return self::ALIASES[$key] ?? $value;
    }

    /**
     * "$17.50", "A$25.00", "SGD 45.00", "¥1,500", "₩144,000".
     *
     * With $compact, a whole amount drops its zero decimals ("$40") — the
     * form the stored price_range strings and the event cards use.
     */
    public static function format(int|float|string $amount, ?string $code, bool $compact = false): string
    {
        $amount = (float) $amount;
        $code = self::resolve($code);

        $precision = ($compact && floor($amount) == $amount) ? 0 : self::decimals($code);

        $formatted = Number::currency($amount, in: $code, locale: self::LOCALE, precision: $precision);

        // ICU puts a non-breaking space between a code and its number
        // ("SGD\u{A0}45.00"). These strings are stored (events.price_range),
        // compared in tests and read by humans in the database, and an
        // invisible NBSP in all of those is a trap; the browser-side
        // formatter makes the same substitution so both sides agree byte
        // for byte.
        return str_replace(["\u{A0}", "\u{202F}"], ' ', $formatted);
    }

    /**
     * How many decimal places a price in this currency is written with, per
     * CLDR — 2 for most, 0 for JPY/KRW — capped at MAX_DECIMALS.
     */
    public static function decimals(?string $code): int
    {
        $formatter = self::formatter();
        $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, self::resolve($code));

        return min(self::MAX_DECIMALS, (int) $formatter->getAttribute(NumberFormatter::FRACTION_DIGITS));
    }

    /**
     * The prefix ICU prints before the number: "$", "A$", "₹", or the code
     * itself ("SGD") when there is no well-known symbol.
     */
    public static function symbol(?string $code): string
    {
        $formatter = self::formatter();
        $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, self::resolve($code));

        // Same plain-space substitution as format(): ICU writes "F CFA" with
        // a narrow non-breaking space inside it.
        return str_replace(["\u{A0}", "\u{202F}"], ' ', $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL));
    }

    /**
     * The currency of an ISO 3166-1 alpha-2 country, or null if unknown.
     * What the wizard and the MCP tool default a new event's tickets to.
     */
    public static function forCountry(?string $country): ?string
    {
        if (! is_string($country) || trim($country) === '') {
            return null;
        }

        $key = mb_strtoupper(trim($country));
        $key = self::COUNTRY_NAMES[$key] ?? $key;

        return self::countries()[$key] ?? null;
    }

    /**
     * The code to format in: the value itself if it is a code, the code a
     * legacy symbol maps to ('£' → GBP — a row the ISO migration has not
     * reached yet, which on deploy is the few seconds between the code going
     * live and the migration finishing), and the default for anything else.
     */
    private static function resolve(?string $code): string
    {
        $normalized = self::normalize($code);

        return self::isValid($normalized) ? $normalized : self::DEFAULT;
    }

    /**
     * @return array<string, string>
     */
    private static function countries(): array
    {
        return self::$countries ??= self::readJson(self::COUNTRY_FILE);
    }

    private static function formatter(): NumberFormatter
    {
        return self::$formatter ??= new NumberFormatter(self::LOCALE, NumberFormatter::CURRENCY);
    }

    private static function readJson(string $file): array
    {
        return json_decode(file_get_contents(resource_path($file)), true, 512, JSON_THROW_ON_ERROR);
    }
}
