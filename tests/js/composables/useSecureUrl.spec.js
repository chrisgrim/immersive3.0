import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSecureUrl } from '@/composables/useSecureUrl.js';

/**
 * useSecureUrl decides whether a link is internal (same site / subdomain) or
 * external, and returns the appropriate target / rel attributes for safe
 * external linking. Internal links get null target/rel; external links open in
 * a new tab with `noopener noreferrer nofollow`.
 *
 * The composable reads `window.location.hostname` / `.origin`, so each test
 * pins location to a known host first.
 */

/** Override window.location with a writable stub for the given origin/hostname. */
function setLocation({ origin, hostname }) {
    Object.defineProperty(window, 'location', {
        configurable: true,
        writable: true,
        value: {
            origin,
            hostname,
            href: `${origin}/`,
        },
    });
}

describe('useSecureUrl', () => {
    beforeEach(() => {
        setLocation({ origin: 'https://everythingimmersive.com', hostname: 'everythingimmersive.com' });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    describe('empty / falsy input', () => {
        it('treats an empty string as internal with no attributes', () => {
            expect(useSecureUrl('')).toEqual({ isInternal: true, target: null, rel: null });
        });

        it('treats null/undefined as internal with no attributes', () => {
            expect(useSecureUrl(null)).toEqual({ isInternal: true, target: null, rel: null });
            expect(useSecureUrl(undefined)).toEqual({ isInternal: true, target: null, rel: null });
        });
    });

    describe('relative URLs', () => {
        it('treats a root-relative path as internal', () => {
            const result = useSecureUrl('/events/some-event');
            expect(result.isInternal).toBe(true);
            expect(result.target).toBeNull();
            expect(result.rel).toBeNull();
        });

        it('treats a bare slash as internal', () => {
            expect(useSecureUrl('/')).toEqual({ isInternal: true, target: null, rel: null });
        });
    });

    describe('internal absolute URLs', () => {
        it('detects an exact same-host absolute URL as internal', () => {
            const result = useSecureUrl('https://everythingimmersive.com/communities/foo');
            expect(result.isInternal).toBe(true);
            expect(result.target).toBeNull();
            expect(result.rel).toBeNull();
        });

        it('detects a subdomain of the current host as internal', () => {
            const result = useSecureUrl('https://blog.everythingimmersive.com/post');
            expect(result.isInternal).toBe(true);
            expect(result.target).toBeNull();
            expect(result.rel).toBeNull();
        });

        it('detects the apex when current host is a subdomain (parent-domain match) as internal', () => {
            setLocation({
                origin: 'https://www.everythingimmersive.com',
                hostname: 'www.everythingimmersive.com',
            });
            const result = useSecureUrl('https://everythingimmersive.com/home');
            expect(result.isInternal).toBe(true);
            expect(result.target).toBeNull();
            expect(result.rel).toBeNull();
        });
    });

    describe('external absolute URLs', () => {
        it('flags a different domain as external with safe target/rel', () => {
            const result = useSecureUrl('https://evil.example.com/phish');
            expect(result.isInternal).toBe(false);
            expect(result.target).toBe('_blank');
            expect(result.rel).toBe('noopener noreferrer nofollow');
        });

        it('does not treat a domain that merely contains the host substring as internal', () => {
            // "everythingimmersive.com.attacker.net" must NOT match — guards
            // against naive substring checks (endsWith requires the dot prefix).
            const result = useSecureUrl('https://everythingimmersive.com.attacker.net/');
            expect(result.isInternal).toBe(false);
            expect(result.target).toBe('_blank');
            expect(result.rel).toBe('noopener noreferrer nofollow');
        });

        it('flags an http external link as external', () => {
            const result = useSecureUrl('http://google.com');
            expect(result.isInternal).toBe(false);
            expect(result.target).toBe('_blank');
        });
    });

    describe('invalid input', () => {
        it('falls back to internal (fail-safe) when URL parsing throws', () => {
            const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
            // A protocol-only / malformed value relative to a valid base still
            // parses in some engines, so force a throw by stubbing URL.
            const OriginalURL = global.URL;
            vi.stubGlobal(
                'URL',
                class {
                    constructor() {
                        throw new Error('bad url');
                    }
                }
            );

            const result = useSecureUrl('https://example.com/whatever');
            expect(result).toEqual({ isInternal: true, target: null, rel: null });
            expect(warnSpy).toHaveBeenCalled();

            vi.stubGlobal('URL', OriginalURL);
        });
    });
});
