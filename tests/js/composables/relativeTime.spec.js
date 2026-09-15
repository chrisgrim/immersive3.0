import { describe, expect, it } from 'vitest';
import moment from 'moment-timezone';
import { fromNow } from '@/composables/relativeTime.js';

/**
 * fromNow replaced moment(when).fromNow() in the nav's recent-searches list
 * so moment-timezone stops loading on every page. The requirement was that
 * visitors see exactly the strings they saw before, so this spec checks the
 * port against the real moment (still a dependency of the creation wizard)
 * rather than against hand-written expectations. dayjs's relativeTime plugin
 * failed this at month boundaries ("a month ago" vs "2 months ago").
 */
describe('relativeTime.fromNow', () => {
    const NOW = new Date('2026-09-14T12:00:00Z');

    it('matches moment on the documented month-boundary case', () => {
        expect(fromNow('2026-07-30T06:00:00Z', NOW)).toBe('2 months ago');
        expect(moment('2026-07-30T06:00:00Z').from(moment(NOW))).toBe('2 months ago');
    });

    it('covers every phrase moment can produce, past and future', () => {
        const seconds = [0, 5, 44, 45, 89, 90, 600, 2640, 2700, 5400, 79200, 86400, 129600, 2246400, 2250000, 3888000, 28512000, 31536000, 47000000, 100000000];
        for (const s of seconds) {
            for (const sign of [1, -1]) {
                const d = new Date(NOW.getTime() - sign * s * 1000);
                expect(fromNow(d, NOW), `${sign * s}s`).toBe(moment(d).from(moment(NOW)));
            }
        }
    });

    it('matches moment across 30,000 random instants around several "now"s', () => {
        const nows = [NOW, new Date('2026-01-31T23:30:00Z'), new Date('2024-02-29T08:00:00Z'), new Date('2026-03-08T09:30:00Z'), new Date('2026-11-01T08:30:00Z')];
        let seed = 42;
        const random = () => {
            seed = (seed * 1664525 + 1013904223) % 4294967296;

            return seed / 4294967296;
        };
        for (const now of nows) {
            for (let i = 0; i < 6000; i++) {
                const span = random() < 0.3 ? 3600 : 4 * 365 * 86400;
                const secs = (random() < 0.5 ? 1 : -1) * Math.floor(random() * span);
                const d = new Date(now.getTime() - secs * 1000);
                expect(fromNow(d, now), d.toISOString()).toBe(moment(d).from(moment(now)));
            }
        }
    });

    it('accepts the API timestamp formats the list receives', () => {
        expect(fromNow('2026-09-03T12:00:00.000000Z', NOW)).toBe('11 days ago');
        expect(fromNow(new Date('2026-09-14T11:59:30Z'), NOW)).toBe('a few seconds ago');
        expect(fromNow(NOW.getTime() - 3 * 3600 * 1000, NOW)).toBe('3 hours ago');
    });
});
