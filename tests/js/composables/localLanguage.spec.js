import { afterEach, describe, expect, it, vi } from 'vitest';
import { localLanguageFor, browserSpeaks } from '@/composables/localLanguage.js';

/**
 * Addresses are saved in the language of the country they are in, not the
 * language of the browser that typed them (a London event once saved its
 * country as "Великобритания").
 */
describe('localLanguageFor', () => {
    it.each([
        ['GB', 'en'], ['US', 'en'], ['DE', 'de'], ['ES', 'es'], ['BG', 'bg'],
        ['JP', 'ja'], ['CN', 'zh-CN'], ['TW', 'zh-TW'], ['BR', 'pt'],
    ])('%s addresses are written in %s', (country, language) => {
        expect(localLanguageFor(country)).toBe(language);
    });

    it('uses English where addresses are usually written in English', () => {
        expect(localLanguageFor('IN')).toBe('en');
        expect(localLanguageFor('PK')).toBe('en');
    });

    it('accepts lower case and gives up on junk', () => {
        expect(localLanguageFor('de')).toBe('de');
        expect(localLanguageFor('')).toBeNull();
        expect(localLanguageFor(null)).toBeNull();
        expect(localLanguageFor('United States')).toBeNull();
    });
});

describe('browserSpeaks', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('matches on the base language, or the full tag for Chinese', () => {
        vi.stubGlobal('navigator', { language: 'en-US' });
        expect(browserSpeaks('en')).toBe(true);
        expect(browserSpeaks('ru')).toBe(false);

        vi.stubGlobal('navigator', { language: 'zh-TW' });
        expect(browserSpeaks('zh-TW')).toBe(true);
        expect(browserSpeaks('zh-CN')).toBe(false);
    });
});
