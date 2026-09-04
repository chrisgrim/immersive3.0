/**
 * Specs for composables/useShowDates.js — a show's calendar day in the
 * EVENT's timezone. The old code handed the raw UTC string to dayjs / new
 * Date() as local wall time, so an evening US show read as the next day.
 */
import { showDay, showDayAsDate, formatShowDay, isShowUpcoming, usesCurtainTimes } from '@/composables/useShowDates';

describe('useShowDates', () => {
    it('reads an evening US show on the day it plays, not the next UTC day', () => {
        expect(showDay('2026-11-01 01:00:00', 'America/Chicago')).toBe('2026-10-31');
        expect(formatShowDay('2026-11-01 01:00:00', 'America/Chicago')).toBe('Oct 31, 2026');
    });

    it('leaves a European evening alone (no rollover)', () => {
        // 20:00 in Paris on Oct 31 is 19:00 UTC the same day.
        expect(showDay('2026-10-31 19:00:00', 'Europe/Paris')).toBe('2026-10-31');
    });

    it('rolls a New Zealand morning FORWARD from the previous UTC day', () => {
        // 9 AM NZDT (UTC+13) on Nov 1 is 20:00 UTC on Oct 31.
        expect(showDay('2026-10-31 20:00:00', 'Pacific/Auckland')).toBe('2026-11-01');
    });

    it('is untouched by the visitor: the noon-local convention reads the same everywhere', () => {
        // The CMS stores shows at noon local; 12:00 Chicago = 17:00 UTC.
        expect(showDay('2026-10-31 17:00:00', 'America/Chicago')).toBe('2026-10-31');
    });

    it('turns the day into a Date at browser-local midnight of that calendar day', () => {
        const day = showDayAsDate('2026-11-01 01:00:00', 'America/Chicago');

        expect(day.getFullYear()).toBe(2026);
        expect(day.getMonth()).toBe(9); // October
        expect(day.getDate()).toBe(31);
        expect(day.getHours()).toBe(0);
    });

    it('accepts any display format and returns nothing for nothing', () => {
        expect(formatShowDay('2026-11-01 01:00:00', 'America/Chicago', 'MMMM D, YYYY')).toBe('October 31, 2026');
        expect(formatShowDay(null, 'America/Chicago')).toBe('');
        expect(showDayAsDate(null, 'America/Chicago')).toBeNull();
    });

    it('counts a show as upcoming by its day, so tonight still counts after noon', () => {
        // "Now": Oct 31, 3 PM in the browser. The show's day is Oct 31.
        const now = new Date(2026, 9, 31, 15, 0, 0);

        expect(isShowUpcoming('2026-11-01 01:00:00', 'America/Chicago', now)).toBe(true);
        expect(isShowUpcoming('2026-10-31 17:00:00', 'America/Chicago', now)).toBe(true);
        expect(isShowUpcoming('2026-10-30 17:00:00', 'America/Chicago', now)).toBe(false);
    });

    it('reads a midnight row as a date unless the schedule records real times', () => {
        const dateOnly = [{ date: '2030-06-01 00:00:00' }, { date: '2030-06-08 00:00:00' }];
        const curtain = [{ date: '2030-06-01 00:00:00' }, { date: '2030-06-08 01:00:00' }];

        expect(usesCurtainTimes(dateOnly)).toBe(false);
        expect(usesCurtainTimes(curtain)).toBe(true);
        expect(usesCurtainTimes([])).toBe(false);
        expect(usesCurtainTimes(undefined)).toBe(false);

        // The wizard until late 2025, and an assistant sending a list of dates.
        expect(showDay('2030-06-01 00:00:00', 'America/Chicago', usesCurtainTimes(dateOnly))).toBe('2030-06-01');
        // The same value among curtain times is 7 PM Central the evening before.
        expect(showDay('2030-06-01 00:00:00', 'America/Chicago', usesCurtainTimes(curtain))).toBe('2030-05-31');
        // A value with no schedule behind it is a date.
        expect(showDay('2030-06-01 00:00:00', 'America/Chicago')).toBe('2030-06-01');
        expect(formatShowDay('2030-06-01 00:00:00', 'America/Chicago', 'MMM D, YYYY')).toBe('Jun 1, 2030');
    });

    it('falls back to UTC for an unknown timezone rather than throwing', () => {
        vi.spyOn(console, 'warn').mockImplementation(() => {});

        expect(showDay('2026-11-01 01:00:00', 'Mars/Olympus')).toBe('2026-11-01');

        console.warn.mockRestore();
    });
});
