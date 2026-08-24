import { describe, expect, it, beforeAll, afterAll } from 'vitest';
import {
    parseSearchDate,
    formatSearchDate,
    isSameSearchDate,
    formatSearchDateRangeLabel,
} from '@/composables/useSavedSearchDateRange.js';

/**
 * Covers the exact bug class flagged in review: `new Date('YYYY-MM-DD
 * 00:00:00')` isn't a portable ECMAScript format, and even where a browser
 * accepts it, treating the stored value as UTC and reading it back in a
 * negative-UTC-offset timezone shifts the displayed day backward by one.
 * These specs force process.env.TZ to America/Los_Angeles (a real
 * negative-offset zone) for exactly that reason — a naive implementation
 * would fail here even though it might pass under the runner's default TZ.
 */
describe('useSavedSearchDateRange', () => {
    let originalTz;

    beforeAll(() => {
        originalTz = process.env.TZ;
        process.env.TZ = 'America/Los_Angeles';
    });

    afterAll(() => {
        process.env.TZ = originalTz;
    });

    describe('parseSearchDate', () => {
        it('returns null for falsy/empty input', () => {
            expect(parseSearchDate(null)).toBeNull();
            expect(parseSearchDate(undefined)).toBeNull();
            expect(parseSearchDate('')).toBeNull();
        });

        it('parses the full stored format without a day shift under a negative UTC offset', () => {
            const date = parseSearchDate('2026-01-01 00:00:00');
            expect(date.getFullYear()).toBe(2026);
            expect(date.getMonth()).toBe(0);
            expect(date.getDate()).toBe(1);
        });

        it('parses a bare YYYY-MM-DD date (no time suffix)', () => {
            const date = parseSearchDate('2026-12-31');
            expect(date.getFullYear()).toBe(2026);
            expect(date.getMonth()).toBe(11);
            expect(date.getDate()).toBe(31);
        });

        it('returns null for a malformed/too-short string', () => {
            expect(parseSearchDate('not-a-date')).toBeNull();
            expect(parseSearchDate('2026-01')).toBeNull();
        });

        it('returns null for a calendar date that does not exist (rolls over)', () => {
            // A hand-edited or legacy row could contain this — Date silently
            // rolls Feb 30 into March 2nd instead of throwing, which would
            // otherwise display the wrong day without any indication.
            expect(parseSearchDate('2026-02-30 00:00:00')).toBeNull();
        });
    });

    describe('formatSearchDate', () => {
        it('formats a local-midnight Date back to the canonical string', () => {
            const date = new Date(2026, 5, 27); // June 27, 2026, local midnight
            expect(formatSearchDate(date)).toBe('2026-06-27 00:00:00');
        });

        it('reads the LOCAL calendar day, not a UTC-shifted one, near a UTC day boundary', () => {
            // At any time on 2026-01-01 local, this must format as
            // 2026-01-01 — a bug here would show 2025-12-31 instead, since
            // this instant is already 2026-01-01 08:00 UTC in
            // America/Los_Angeles (UTC-8), close enough to the UTC
            // boundary that a naive toISOString()-of-the-original-instant
            // approach could plausibly get this right OR wrong depending
            // on the hour — using local getFullYear/getMonth/getDate
            // (as this function does) is correct regardless of hour.
            const date = new Date(2026, 0, 1, 23, 0, 0);
            expect(formatSearchDate(date)).toBe('2026-01-01 00:00:00');
        });
    });

    describe('parseSearchDate + formatSearchDate round-trip', () => {
        it('produces the exact original string for any stored date, regardless of TZ', () => {
            for (const original of ['2026-01-01 00:00:00', '2026-06-27 00:00:00', '2026-12-31 00:00:00']) {
                expect(formatSearchDate(parseSearchDate(original))).toBe(original);
            }
        });
    });

    describe('isSameSearchDate', () => {
        it('treats equal strings as the same date', () => {
            expect(isSameSearchDate('2026-06-27 00:00:00', '2026-06-27 00:00:00')).toBe(true);
        });

        it('ignores a differing time suffix — only the calendar day matters', () => {
            expect(isSameSearchDate('2026-06-27 00:00:00', '2026-06-27 12:30:00')).toBe(true);
        });

        it('returns false for different days', () => {
            expect(isSameSearchDate('2026-06-27 00:00:00', '2026-06-28 00:00:00')).toBe(false);
        });

        it('handles null on either side without throwing', () => {
            expect(isSameSearchDate(null, null)).toBe(true);
            expect(isSameSearchDate('2026-06-27 00:00:00', null)).toBe(false);
            expect(isSameSearchDate(null, '2026-06-27 00:00:00')).toBe(false);
        });
    });

    describe('formatSearchDateRangeLabel', () => {
        it('returns null when there is no start date', () => {
            expect(formatSearchDateRangeLabel(null, null)).toBeNull();
            expect(formatSearchDateRangeLabel(null, '2026-06-27 00:00:00')).toBeNull();
        });

        it('shows a single formatted date when start === end', () => {
            expect(formatSearchDateRangeLabel('2026-06-27 00:00:00', '2026-06-27 00:00:00')).toBe('Jun 27');
        });

        it('shows a single formatted date when end is missing entirely', () => {
            expect(formatSearchDateRangeLabel('2026-06-27 00:00:00', null)).toBe('Jun 27');
        });

        it('shows a range when start and end differ', () => {
            expect(formatSearchDateRangeLabel('2026-06-27 00:00:00', '2026-06-29 00:00:00')).toBe('Jun 27 – Jun 29');
        });

        it('shows a range that spans months correctly', () => {
            expect(formatSearchDateRangeLabel('2026-06-29 00:00:00', '2026-07-02 00:00:00')).toBe('Jun 29 – Jul 2');
        });
    });
});
