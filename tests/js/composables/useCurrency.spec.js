import { describe, expect, it } from 'vitest';
import {
    CURRENCY_CODES,
    DEFAULT_CURRENCY,
    currencyDecimals,
    currencyForCountry,
    currencyName,
    currencyPrefix,
    currencySymbol,
    formatPrice,
    isCurrencyCode,
} from '@/composables/useCurrency';

/**
 * useCurrency is the browser twin of App\Support\Currency. The strings pinned
 * here are the SAME strings tests/Feature/CurrencyCatalogTest.php pins for the
 * PHP side — that agreement is the whole contract: a price the wizard shows
 * while editing is what the live page will print after saving.
 */
describe('useCurrency', () => {
    it('reads a list of current ISO 4217 codes', () => {
        expect(CURRENCY_CODES.length).toBeGreaterThan(150);
        for (const code of ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'MXN', 'CNY', 'KRW', 'AUD', 'HKD', 'TWD', 'THB', 'SGD', 'INR']) {
            expect(isCurrencyCode(code)).toBe(true);
        }
        expect(isCurrencyCode('$')).toBe(false);
        expect(isCurrencyCode('usd')).toBe(false);
        // Retired codes ICU still carries are pruned from the list.
        expect(isCurrencyCode('ZWL')).toBe(false);
        expect(isCurrencyCode('HRK')).toBe(false);
        expect(isCurrencyCode('ZWG')).toBe(true);
        expect(isCurrencyCode(null)).toBe(false);
        expect(DEFAULT_CURRENCY).toBe('USD');
    });

    it('formats exactly as the server does', () => {
        expect(formatPrice(17.5, 'USD')).toBe('$17.50');
        expect(formatPrice(25, 'AUD')).toBe('A$25.00');
        expect(formatPrice(25, 'CAD')).toBe('CA$25.00');
        expect(formatPrice(25, 'HKD')).toBe('HK$25.00');
        expect(formatPrice(25, 'TWD')).toBe('NT$25.00');
        expect(formatPrice(25, 'MXN')).toBe('MX$25.00');
        expect(formatPrice(99.5, 'CNY')).toBe('CN¥99.50');
        expect(formatPrice(1500, 'JPY')).toBe('¥1,500');
        expect(formatPrice(144000, 'KRW')).toBe('₩144,000');
        expect(formatPrice(0.99, 'EUR')).toBe('€0.99');
        expect(formatPrice(500, 'INR')).toBe('₹500.00');
        // Code plus a PLAIN space, not the non-breaking one Intl emits.
        expect(formatPrice(45, 'SGD')).toBe('SGD 45.00');
        expect(formatPrice(1200, 'THB')).toBe('THB 1,200.00');
    });

    it('accepts the fixed 2-decimal strings the API sends for ticket_price', () => {
        expect(formatPrice('1500.00', 'JPY')).toBe('¥1,500');
        expect(formatPrice('17.50', 'USD')).toBe('$17.50');
        expect(formatPrice('', 'USD')).toBe('');
        expect(formatPrice('abc', 'USD')).toBe('');
    });

    it('drops zero decimals from a whole amount only when asked', () => {
        expect(formatPrice(40, 'USD', { compact: true })).toBe('$40');
        expect(formatPrice(17.5, 'USD', { compact: true })).toBe('$17.50');
        expect(formatPrice(45, 'SGD', { compact: true })).toBe('SGD 45');
        expect(formatPrice(40, 'USD')).toBe('$40.00');
    });

    it('treats a missing currency as USD and an unmapped legacy value verbatim', () => {
        expect(formatPrice(10, null)).toBe('$10.00');
        expect(formatPrice(10, '')).toBe('$10.00');
        // A row the ISO migration could not map keeps its old prefix form
        // rather than silently becoming dollars — same as the server.
        expect(formatPrice(25, 'XX')).toBe('XX25.00');
    });

    it('takes decimal places from CLDR', () => {
        expect(currencyDecimals('USD')).toBe(2);
        expect(currencyDecimals('JPY')).toBe(0);
        expect(currencyDecimals('KRW')).toBe(0);
        expect(currencyDecimals('CNY')).toBe(2);
        expect(currencyDecimals('KWD')).toBe(3);
        expect(currencyDecimals(undefined)).toBe(2);
    });

    it('gives the prefix the number will carry, falling back to the code', () => {
        expect(currencySymbol('USD')).toBe('$');
        expect(currencySymbol('AUD')).toBe('A$');
        expect(currencySymbol('INR')).toBe('₹');
        expect(currencySymbol('SGD')).toBe('SGD');
    });

    it('uses the code as a large prefix when the symbol is a phrase', () => {
        expect(currencyPrefix('USD')).toBe('$');
        expect(currencyPrefix('AUD')).toBe('A$');
        expect(currencyPrefix('CAD')).toBe('CA$');
        expect(currencyPrefix('SGD')).toBe('SGD');
        // ICU's English symbol is "F CFA" — five characters, one of them a
        // space, which at the wizard's 9.5rem size ran into the number.
        expect(currencySymbol('XOF')).toBe('F CFA');
        expect(currencyPrefix('XOF')).toBe('XOF');
        expect(currencyPrefix('XAF')).toBe('XAF');
        expect(currencyPrefix(null)).toBe('$');
    });

    it('names a currency from the browser, with no list of its own', () => {
        expect(currencyName('SGD')).toBe('Singapore Dollar');
        expect(currencyName('AUD')).toBe('Australian Dollar');
        expect(currencyName('TWD')).toBe('New Taiwan Dollar');
    });

    it('maps a venue country to its currency', () => {
        expect(currencyForCountry('SG')).toBe('SGD');
        expect(currencyForCountry('au')).toBe('AUD');
        expect(currencyForCountry('IN')).toBe('INR');
        expect(currencyForCountry('US')).toBe('USD');
        expect(currencyForCountry('')).toBe(null);
        expect(currencyForCountry(null)).toBe(null);
        expect(currencyForCountry('ZZ')).toBe(null);
    });
});
