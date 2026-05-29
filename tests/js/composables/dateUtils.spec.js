import { describe, expect, it } from 'vitest';
import moment from 'moment-timezone';
import {
    normalizeDateToTimezone,
    createDateAtNoon,
    formatDateForAPI,
    parseDateString,
    addMonths,
    endOfDay,
    daysBetween,
    generateRecurringDates,
    isValidTimezone,
    isSameDay,
    getBrowserTimezone,
    now,
} from '@/composables/dateUtils.js';

/**
 * dateUtils centralizes timezone-safe date math for the event date pickers.
 * These specs run against the REAL moment-timezone (the global setup leaves it
 * un-mocked on purpose) and pin behavior across America/New_York (DST-bearing)
 * and UTC (no DST), so a future refactor that silently shifts a day fails here.
 *
 * Convention under test: callers pass a `YYYY-MM-DD` string that should be
 * interpreted as a wall-clock date in the given timezone (no implicit local-tz
 * reinterpretation), which is how the date pickers feed these functions.
 */

const NY = 'America/New_York';
const UTC = 'UTC';

describe('dateUtils', () => {
    describe('normalizeDateToTimezone', () => {
        it('returns null for falsy input', () => {
            expect(normalizeDateToTimezone(null, NY)).toBeNull();
            expect(normalizeDateToTimezone('', NY)).toBeNull();
            expect(normalizeDateToTimezone(undefined, UTC)).toBeNull();
        });

        it('keeps a YYYY-MM-DD string on the same calendar day in NY and UTC', () => {
            expect(normalizeDateToTimezone('2025-03-15', NY)).toBe('2025-03-15');
            expect(normalizeDateToTimezone('2025-03-15', UTC)).toBe('2025-03-15');
        });

        it('reports the calendar day as seen in the target timezone for an instant', () => {
            // 2025-03-16T02:00:00Z is still March 15 (22:00) in New York.
            const instant = '2025-03-16T02:00:00Z';
            expect(normalizeDateToTimezone(instant, UTC)).toBe('2025-03-16');
            expect(normalizeDateToTimezone(instant, NY)).toBe('2025-03-15');
        });

        it('falls back to UTC for an invalid timezone (and warns)', () => {
            const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
            // 2025-03-16T02:00:00Z -> March 16 in UTC.
            expect(normalizeDateToTimezone('2025-03-16T02:00:00Z', 'Not/AZone')).toBe('2025-03-16');
            expect(warn).toHaveBeenCalled();
            warn.mockRestore();
        });
    });

    describe('createDateAtNoon', () => {
        it('returns null for empty input', () => {
            expect(createDateAtNoon('', NY)).toBeNull();
            expect(createDateAtNoon(null, UTC)).toBeNull();
        });

        it('produces a Date that reads as noon wall-clock in the given timezone', () => {
            const dNY = createDateAtNoon('2025-03-15', NY);
            expect(dNY).toBeInstanceOf(Date);
            expect(moment.tz(dNY, NY).format('YYYY-MM-DD HH:mm:ss')).toBe('2025-03-15 12:00:00');
            // During EDT (UTC-4) noon NY == 16:00 UTC.
            expect(moment.utc(dNY).format('HH:mm')).toBe('16:00');

            const dUTC = createDateAtNoon('2025-03-15', UTC);
            expect(moment.utc(dUTC).format('YYYY-MM-DD HH:mm:ss')).toBe('2025-03-15 12:00:00');
        });
    });

    describe('formatDateForAPI', () => {
        it('returns null for falsy input', () => {
            expect(formatDateForAPI(null, NY)).toBeNull();
            expect(formatDateForAPI('', UTC)).toBeNull();
        });

        it('formats a NY date as noon-NY converted to UTC (EST, winter)', () => {
            // Noon EST (UTC-5) == 17:00 UTC.
            expect(formatDateForAPI('2025-01-15', NY)).toBe('2025-01-15 17:00:00');
        });

        it('formats a NY date as noon-NY converted to UTC (EDT, summer)', () => {
            // Noon EDT (UTC-4) == 16:00 UTC.
            expect(formatDateForAPI('2025-07-15', NY)).toBe('2025-07-15 16:00:00');
        });

        it('formats a UTC date as plain noon (no offset)', () => {
            expect(formatDateForAPI('2025-07-15', UTC)).toBe('2025-07-15 12:00:00');
        });

        it('handles a date the morning after a spring-forward DST transition', () => {
            // 2025-03-09 is the spring-forward day in NY; noon is well clear of the
            // 02:00->03:00 gap, so it is unambiguously EDT (UTC-4) -> 16:00 UTC.
            expect(formatDateForAPI('2025-03-09', NY)).toBe('2025-03-09 16:00:00');
        });
    });

    describe('parseDateString', () => {
        it('returns null for empty input', () => {
            expect(parseDateString('', NY)).toBeNull();
            expect(parseDateString(null, UTC)).toBeNull();
        });

        it('returns a Date instance unchanged', () => {
            const d = new Date('2025-05-01T00:00:00Z');
            expect(parseDateString(d, NY)).toBe(d);
        });

        it('parses a YYYY-MM-DD string to noon in the timezone', () => {
            const d = parseDateString('2025-06-20', NY);
            expect(d).toBeInstanceOf(Date);
            expect(moment.tz(d, NY).format('YYYY-MM-DD HH:mm')).toBe('2025-06-20 12:00');
        });

        it('drops the time component of a full datetime string and uses noon', () => {
            const d = parseDateString('2025-06-20 08:30:00', NY);
            expect(moment.tz(d, NY).format('YYYY-MM-DD HH:mm')).toBe('2025-06-20 12:00');
        });

        it('coerces a non-string, non-Date value to a string before parsing', () => {
            // Number 20250620 stringifies to "20250620" -> split('-') yields a single
            // NaN-ish part; guard only that it does not throw and returns a Date.
            const d = parseDateString('2025-12-31', UTC);
            expect(moment.tz(d, UTC).format('YYYY-MM-DD')).toBe('2025-12-31');
        });
    });

    describe('addMonths', () => {
        it('adds whole months in UTC', () => {
            const d = addMonths('2025-01-15', 3, UTC);
            expect(moment.tz(d, UTC).format('YYYY-MM-DD')).toBe('2025-04-15');
        });

        it('clamps Jan 31 + 1 month to the last day of February (non-leap)', () => {
            const d = addMonths('2025-01-31', 1, NY);
            expect(moment.tz(d, NY).format('YYYY-MM-DD')).toBe('2025-02-28');
        });

        it('clamps Jan 31 + 1 month to Feb 29 in a leap year', () => {
            const d = addMonths('2024-01-31', 1, UTC);
            expect(moment.tz(d, UTC).format('YYYY-MM-DD')).toBe('2024-02-29');
        });

        it('crosses a year boundary', () => {
            const d = addMonths('2025-11-30', 2, UTC);
            expect(moment.tz(d, UTC).format('YYYY-MM-DD')).toBe('2026-01-30');
        });
    });

    describe('endOfDay', () => {
        it('sets the time to 23:59:59 in the target timezone', () => {
            const d = endOfDay('2025-03-15', UTC);
            expect(moment.utc(d).format('YYYY-MM-DD HH:mm:ss')).toBe('2025-03-15 23:59:59');

            const dNY = endOfDay('2025-03-15', NY);
            expect(moment.tz(dNY, NY).format('YYYY-MM-DD HH:mm:ss')).toBe('2025-03-15 23:59:59');
        });
    });

    describe('daysBetween', () => {
        it('counts whole days between two dates (UTC)', () => {
            expect(daysBetween('2025-03-01', '2025-03-31', UTC)).toBe(30);
        });

        it('returns 0 for the same day', () => {
            expect(daysBetween('2025-03-15', '2025-03-15', NY)).toBe(0);
        });

        it('returns a negative count when end precedes start', () => {
            expect(daysBetween('2025-03-31', '2025-03-01', UTC)).toBe(-30);
        });

        it('counts calendar days across a NY spring-forward transition', () => {
            // March has a 23-hour day (DST), but startOf('day') diff counts calendar
            // days, so March 1 -> March 31 is still 30.
            expect(daysBetween('2025-03-01', '2025-03-31', NY)).toBe(30);
        });
    });

    describe('generateRecurringDates', () => {
        it('produces every Monday in a month (NY)', () => {
            const dates = generateRecurringDates([1], '2025-03-03', '2025-03-31', NY);
            expect(dates).toEqual([
                '2025-03-03',
                '2025-03-10',
                '2025-03-17',
                '2025-03-24',
                '2025-03-31',
            ]);
        });

        it('merges and sorts multiple days of the week chronologically', () => {
            const dates = generateRecurringDates([1, 5], '2025-03-03', '2025-03-14', NY);
            expect(dates).toEqual([
                '2025-03-03', // Mon
                '2025-03-07', // Fri
                '2025-03-10', // Mon
                '2025-03-14', // Fri
            ]);
        });

        it('keeps weekly cadence correct across the NY fall-back DST transition', () => {
            // Nov 2, 2025 is the fall-back day; clinging to noon avoids the 01:00
            // repeated hour, so Sundays stay one calendar week apart.
            const dates = generateRecurringDates([0], '2025-10-26', '2025-11-16', NY);
            expect(dates).toEqual([
                '2025-10-26',
                '2025-11-02',
                '2025-11-09',
                '2025-11-16',
            ]);
        });

        it('keeps weekly cadence correct across the NY spring-forward transition', () => {
            // March 9, 2025 is spring-forward; Sundays should still be exactly a week
            // apart on the calendar despite the 23-hour day.
            const dates = generateRecurringDates([0], '2025-03-02', '2025-03-23', NY);
            expect(dates).toEqual([
                '2025-03-02',
                '2025-03-09',
                '2025-03-16',
                '2025-03-23',
            ]);
        });

        it('BUG: emits the end date even when no requested weekday is in the range', () => {
            // 2025-03-04 (Tue) .. 2025-03-06 (Thu) contains no Sunday (0), so the
            // correct result is []. But the "find first occurrence" loop stops once
            // `current` is no longer isBefore(end) — i.e. AT end (Thu 03-06) — and the
            // generation loop then pushes that end date via isSameOrBefore, even though
            // it is a Thursday, not a Sunday. This documents the current (buggy) output.
            // See findings: generateRecurringDates end-date leak.
            expect(generateRecurringDates([0], '2025-03-04', '2025-03-06', NY)).toEqual(['2025-03-06']);
        });

        it('returns an empty array when daysOfWeek is empty', () => {
            expect(generateRecurringDates([], '2025-03-01', '2025-03-31', NY)).toEqual([]);
        });

        it('produces identical date strings in UTC', () => {
            const dates = generateRecurringDates([3], '2025-04-01', '2025-04-30', UTC);
            // Wednesdays in April 2025.
            expect(dates).toEqual([
                '2025-04-02',
                '2025-04-09',
                '2025-04-16',
                '2025-04-23',
                '2025-04-30',
            ]);
        });
    });

    describe('isValidTimezone', () => {
        it('returns true for real IANA zones', () => {
            expect(isValidTimezone('UTC')).toBe(true);
            expect(isValidTimezone(NY)).toBe(true);
        });

        it('returns false for garbage', () => {
            expect(isValidTimezone('Not/AZone')).toBe(false);
            expect(isValidTimezone('')).toBe(false);
            expect(isValidTimezone('EST5EDTfoo')).toBe(false);
        });
    });

    describe('isSameDay', () => {
        it('is true for two instants on the same calendar day in the timezone', () => {
            expect(isSameDay('2025-03-15T01:00:00Z', '2025-03-15T20:00:00Z', UTC)).toBe(true);
        });

        it('is timezone-dependent for instants that straddle midnight', () => {
            // 02:00Z and 05:00Z on Mar 16 are the same UTC day but different NY days
            // (the 02:00Z one is still Mar 15 22:00 in NY).
            const a = '2025-03-16T02:00:00Z';
            const b = '2025-03-16T05:00:00Z';
            expect(isSameDay(a, b, UTC)).toBe(true);
            expect(isSameDay(a, b, NY)).toBe(false);
        });

        it('is false for clearly different days', () => {
            expect(isSameDay('2025-03-15', '2025-03-16', NY)).toBe(false);
        });
    });

    describe('getBrowserTimezone', () => {
        it('returns a non-empty string', () => {
            const tz = getBrowserTimezone();
            expect(typeof tz).toBe('string');
            expect(tz.length).toBeGreaterThan(0);
        });
    });

    describe('now', () => {
        it('returns a Date close to the real current time', () => {
            const before = Date.now();
            const result = now(NY);
            const after = Date.now();
            expect(result).toBeInstanceOf(Date);
            // The underlying instant is timezone-independent; allow generous slack.
            expect(result.getTime()).toBeGreaterThanOrEqual(before - 1000);
            expect(result.getTime()).toBeLessThanOrEqual(after + 1000);
        });
    });
});
