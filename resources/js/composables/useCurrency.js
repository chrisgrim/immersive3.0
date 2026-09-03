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
 * 0 for JPY/KRW, 3 for KWD). Mirrors Currency::decimals.
 */
export const currencyDecimals = (code) =>
    formatterFor(isCurrencyCode(code) ? code : DEFAULT_CURRENCY).resolvedOptions().maximumFractionDigits;

/**
 * The prefix printed before the number: "$", "A$", "₹", or the code itself
 * ("SGD") when there is no well-known symbol. Mirrors Currency::symbol.
 */
export const currencySymbol = (code) => {
    const resolved = isCurrencyCode(code) ? code : DEFAULT_CURRENCY;

    return formatterFor(resolved).formatToParts(0).find((part) => part.type === 'currency')?.value ?? resolved;
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

    const formatter = compact && Number.isInteger(value) ? formatterFor(resolved, 0) : formatterFor(resolved);

    return formatter.format(value).replace(/[  ]/g, ' ');
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
