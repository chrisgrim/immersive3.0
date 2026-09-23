/**
 * The language an address should be written in: the everyday language of the
 * country it is in, not the language of whoever typed it.
 *
 * Google returns place details in the visitor's browser language, so a London
 * event entered from a Russian-language browser saved its country as
 * "Великобритания", while a Berlin event entered from an English browser
 * says "Germany". EI wants each place named the way locals name it
 * ("Deutschland", "España", "中国"), so the location form asks Google again
 * in the language this returns.
 *
 * The browser's own locale data (CLDR "likely subtags") supplies the country's
 * main language. A few countries come back as a language their street
 * addresses are rarely written in (India → Hindi, Pakistan → Urdu, ...);
 * those use English.
 */
const ENGLISH_ADDRESSES = new Set(['IN', 'PK', 'PH', 'KE', 'NG', 'SG', 'MY']);

export function localLanguageFor(countryCode) {
    const code = String(countryCode || '').trim().toUpperCase();
    if (!/^[A-Z]{2}$/.test(code)) return null;
    if (ENGLISH_ADDRESSES.has(code)) return 'en';

    try {
        const locale = new Intl.Locale(`und-${code}`).maximize();
        // Unknown regions maximize to en-US; only trust an answer for this region.
        if (locale.region !== code) return null;
        // "zh-TW" and "zh-CN" differ in script, so keep the region on Chinese.
        return locale.language === 'zh' ? `zh-${code}` : locale.language;
    } catch {
        return null;
    }
}

/** Whether the browser already speaks that language, so no second lookup is needed. */
export function browserSpeaks(language) {
    const browser = (typeof navigator !== 'undefined' && navigator.language) || '';
    if (!language || !browser) return false;
    return language.includes('-')
        ? browser.toLowerCase() === language.toLowerCase()
        : browser.toLowerCase().split('-')[0] === language;
}
