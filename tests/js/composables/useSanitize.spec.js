import { describe, expect, it } from 'vitest';
import { sanitizeBlurb, sanitizeMessage } from '@/composables/useSanitize.js';

/**
 * useSanitize wraps the REAL DOMPurify (intentionally left un-mocked in the
 * global setup) with two allow-list profiles:
 *   - sanitizeBlurb: rich formatting + links (the TipTap blurb editor output)
 *   - sanitizeMessage: only <p>/<br> (plain conversation messages)
 * It also registers an afterSanitizeAttributes hook that hardens any
 * target="_blank" anchor with rel="noopener noreferrer".
 *
 * Note: the hook is module-level/global to DOMPurify, so it is active for both
 * profiles once this module is imported.
 */

describe('useSanitize', () => {
    describe('sanitizeBlurb', () => {
        it('returns an empty string for falsy input', () => {
            expect(sanitizeBlurb('')).toBe('');
            expect(sanitizeBlurb(null)).toBe('');
            expect(sanitizeBlurb(undefined)).toBe('');
        });

        it('keeps allowed formatting tags', () => {
            const out = sanitizeBlurb(
                '<p>para</p><strong>b</strong><em>i</em><ul><li>x</li></ul><h2>h</h2><blockquote>q</blockquote>'
            );
            expect(out).toContain('<p>para</p>');
            expect(out).toContain('<strong>b</strong>');
            expect(out).toContain('<em>i</em>');
            expect(out).toContain('<ul><li>x</li></ul>');
            expect(out).toContain('<h2>h</h2>');
            expect(out).toContain('<blockquote>q</blockquote>');
        });

        it('keeps anchors with their href', () => {
            const out = sanitizeBlurb('<a href="https://example.com">link</a>');
            expect(out).toContain('href="https://example.com"');
            expect(out).toContain('>link</a>');
        });

        it('strips <script> entirely while keeping surrounding content', () => {
            const out = sanitizeBlurb('<p>safe</p><script>alert(1)</script>');
            expect(out).toBe('<p>safe</p>');
            expect(out).not.toContain('script');
            expect(out).not.toContain('alert');
        });

        it('removes disallowed tags (div/img) but keeps their text', () => {
            const out = sanitizeBlurb('<div><img src=x onerror=alert(1)>hello</div>');
            expect(out).toBe('hello');
            expect(out).not.toContain('img');
            expect(out).not.toContain('onerror');
            expect(out).not.toContain('<div>');
        });

        it('drops a javascript: href but keeps the anchor element', () => {
            const out = sanitizeBlurb('<a href="javascript:alert(1)">x</a>');
            expect(out).not.toContain('javascript:');
            expect(out).toContain('>x</a>');
        });

        it('adds rel="noopener noreferrer" to a target="_blank" link (DOMPurify hook)', () => {
            const out = sanitizeBlurb('<a href="https://external.example/path" target="_blank">go</a>');
            expect(out).toContain('target="_blank"');
            expect(out).toContain('rel="noopener noreferrer"');
        });

        it('does not add a rel to a link without target="_blank"', () => {
            const out = sanitizeBlurb('<a href="https://external.example/path">go</a>');
            expect(out).not.toContain('rel=');
        });
    });

    describe('sanitizeMessage', () => {
        it('returns an empty string for falsy input', () => {
            expect(sanitizeMessage('')).toBe('');
            expect(sanitizeMessage(null)).toBe('');
            expect(sanitizeMessage(undefined)).toBe('');
        });

        it('allows only <p> and <br>', () => {
            const out = sanitizeMessage('<p>Hi<br>there</p>');
            expect(out).toContain('<p>');
            expect(out).toContain('<br>');
            expect(out).toContain('Hi');
            expect(out).toContain('there');
        });

        it('strips formatting tags allowed in blurbs but not in messages', () => {
            const out = sanitizeMessage('<strong>bold</strong><em>i</em><a href="https://x.com">l</a>');
            // Tags removed, text content preserved.
            expect(out).toBe('boldil');
            expect(out).not.toContain('strong');
            expect(out).not.toContain('<em>');
            expect(out).not.toContain('href');
        });

        it('strips <script> from a message', () => {
            const out = sanitizeMessage('<script>alert(1)</script><p>ok</p>');
            expect(out).toBe('<p>ok</p>');
            expect(out).not.toContain('script');
        });
    });
});
