/**
 * Ticket currencies: ISO 4217 codes, rendered by the browser's own CLDR data.
 *
 * The PHP twin is App\Support\Currency. Both read the same two JSON files
 * (resources/data) and both format in the same fixed 'en' locale, so a price
 * string built here is byte-for-byte what the server builds — "$17.50",
 * "A$25.00", "SGD 45.00", "¥1,500", "₩144,000". There is no list of symbols
 * or decimals to keep in step any more; the platform knows them.
 */
import CURRENCY_CODES from '../../data/currencies.json';
import COUNTRY_CURRENCY from '../../data/country-currency.json';

// Same as Currency::LOCALE. Never the viewer's locale: bare "$" means USD.
const LOCALE = 'en';

export const DEFAULT_CURRENCY = 'USD';

// The most decimals a stored price can carry (tickets.ticket_price is
// decimal(8,2)). CLDR writes KWD, BHD, JOD… with three; see Currency::MAX_DECIMALS.
export const MAX_DECIMALS = 2;

export { CURRENCY_CODES };

export const isCurrencyCode = (code) => typeof code === 'string' && CURRENCY_CODES.includes(code);

const formatters = new Map();

const formatterFor = (code, fractionDigits) => {
    const key = `${code}|${fractionDigits ?? ''}`;

    if (!formatters.has(key)) {
        const options = { style: 'currency', currency: code };
        if (fractionDigits !== undefined) {
            options.minimumFractionDigits = fractionDigits;
            options.maximumFractionDigits = fractionDigits;
        }
        formatters.set(key, new Intl.NumberFormat(LOCALE, options));
    }

    return formatters.get(key);
};

/**
 * How many decimals a price in this currency is written with (2 for most,
 * 0 for JPY/KRW), capped at MAX_DECIMALS. Mirrors Currency::decimals.
 */
export const currencyDecimals = (code) =>
    Math.min(MAX_DECIMALS, formatterFor(isCurrencyCode(code) ? code : DEFAULT_CURRENCY).resolvedOptions().maximumFractionDigits);

/**
 * The prefix printed before the number: "$", "A$", "₹", or the code itself
 * ("SGD") when there is no well-known symbol. Mirrors Currency::symbol.
 */
export const currencySymbol = (code) => {
    const resolved = isCurrencyCode(code) ? code : DEFAULT_CURRENCY;

    const symbol = formatterFor(resolved).formatToParts(0).find((part) => part.type === 'currency')?.value ?? resolved;

    // Same plain-space substitution as formatPrice: ICU writes "F CFA" with a
    // narrow non-breaking space inside it.
    return symbol.replace(/[\u00A0\u202F]/g, ' ');
};

/**
 * What to print as a large stand-alone prefix, as in the wizard's giant
 * price input: the symbol when it is short ("$", "A$", "CA$", "SGD"), the
 * code when ICU's symbol is a phrase ("F CFA" for XOF, "FCFA" for XAF) —
 * at a 9.5rem font a five-character symbol collides with the number.
 */
export const currencyPrefix = (code) => {
    const symbol = currencySymbol(code);

    return symbol.length <= 3 ? symbol : (isCurrencyCode(code) ? code : DEFAULT_CURRENCY);
};

let displayNames = null;

/** "Singapore Dollar", "Australian Dollar" — from the browser, no list. */
export const currencyName = (code) => {
    try {
        displayNames ??= new Intl.DisplayNames([LOCALE], { type: 'currency' });

        return displayNames.of(code) ?? code;
    } catch {
        return code;
    }
};

/**
 * Format an amount in a currency. With `compact`, a whole amount drops its
 * zero decimals ("$40") — the form the event cards use. Mirrors
 * Currency::format, including the plain space between a code and its
 * number where ICU would put a non-breaking one.
 *
 * A stored value that is not a code (only possible for a row the ISO
 * migration could not map) keeps the old verbatim-prefix form rather than
 * being silently shown as dollars — same fallback as the server.
 */
export const formatPrice = (amount, code, { compact = false } = {}) => {
    // An empty price input is empty, not $0.00 — Number('') is 0.
    if (amount === '' || amount === null || amount === undefined) return '';

    const value = Number(amount);
    if (!Number.isFinite(value)) return '';

    const resolved = code ? code : DEFAULT_CURRENCY;
    if (!isCurrencyCode(resolved)) {
        return `${resolved}${compact && Number.isInteger(value) ? value : value.toFixed(2)}`;
    }

    const formatter = formatterFor(resolved, compact && Number.isInteger(value) ? 0 : currencyDecimals(resolved));

    return formatter.format(value).replace(/[\u00A0\u202F]/g, ' ');
};

/**
 * The currency of an ISO 3166-1 alpha-2 country, or null — what the wizard
 * defaults a new event's tickets to from its venue. Mirrors
 * Currency::forCountry (minus the legacy English-name fallback, which only
 * old rows need and the wizard never writes).
 */
export const currencyForCountry = (country) => {
    if (typeof country !== 'string') return null;

    return COUNTRY_CURRENCY[country.trim().toUpperCase()] ?? null;
};
