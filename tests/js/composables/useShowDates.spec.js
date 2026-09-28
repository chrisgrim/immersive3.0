/**
 * Specs for composables/useShowDates.js — a show's calendar day in the
 * EVENT's timezone. The old code handed the raw UTC string to dayjs / new
 * Date() as local wall time, so an evening US show read as the next day.
 */
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import { showDay, showDayAsDate, formatShowDay, isShowUpcoming, usesCurtainTimes, eventUsesCurtainTimes, formatShowDayRange, summarizeSchedule, showHistoryDays, showHistoryDates, datesNearMonths } from '@/composables/useShowDates';

dayjs.extend(utc);

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

/**
 * The sentinel showtypes ('a' always available, 'l' the retired limited type)
 * store ONE row that is the listing's END DATE, not a performance. Rendering
 * it like a real schedule told a moderator "Mar 7, 2027 / 1 show", i.e. the
 * event happens once on that date — the opposite of what it means.
 */
describe('summarizeSchedule', () => {
    const alwaysAvailable = {
        showtype: 'a',
        timezone: 'America/Los_Angeles',
        shows: [{ date: '2027-03-07 12:00:00' }],
    };

    it('reads an always-available listing as available, not as a one-off', () => {
        expect(summarizeSchedule(alwaysAvailable)).toEqual({
            primary: 'Always available',
            secondary: 'Searchable until Mar 7, 2027',
        });
    });

    it('does the same for the retired limited type', () => {
        expect(summarizeSchedule({ ...alwaysAvailable, showtype: 'l' })).toEqual({
            primary: 'Limited run',
            secondary: 'Searchable until Mar 7, 2027',
        });
    });

    it('says so when a sentinel listing has no row to read an end date from', () => {
        expect(summarizeSchedule({ ...alwaysAvailable, shows: [] })).toEqual({
            primary: 'Always available',
            secondary: 'No end date set',
        });
    });

    it('treats a sentinel row with no readable date as having no end date', () => {
        expect(summarizeSchedule({ ...alwaysAvailable, shows: [{}] })).toEqual({
            primary: 'Always available',
            secondary: 'No end date set',
        });
    });

    it('still counts real performances for specific dates', () => {
        expect(summarizeSchedule({
            showtype: 's',
            timezone: 'Europe/London',
            shows: [{ date: '2026-09-14 12:00:00' }, { date: '2026-09-28 12:00:00' }],
        })).toEqual({
            primary: 'Sep 14, 2026 - Sep 28, 2026',
            secondary: '2 shows',
        });
    });

    it('keeps the singular for a one-date run, which is genuinely one show', () => {
        expect(summarizeSchedule({
            showtype: 's',
            timezone: 'Europe/London',
            shows: [{ date: '2026-09-14 12:00:00' }],
        })).toEqual({
            primary: 'Sep 14, 2026',
            secondary: '1 show',
        });
    });

    it('survives an event with no schedule at all', () => {
        expect(summarizeSchedule(undefined)).toEqual({
            primary: 'No dates set',
            secondary: '0 shows',
        });
    });

    it('reads the range in the EVENT timezone, so an evening show keeps its own day', () => {
        // 01:00 UTC on Nov 1 is 8 PM Oct 31 in Chicago.
        expect(formatShowDayRange([{ date: '2026-11-01 01:00:00' }], 'America/Chicago'))
            .toBe('Oct 31, 2026');
    });
});

describe('eventUsesCurtainTimes', () => {
    it('prefers the whole-run flag the server sends, since the page embeds only upcoming rows', () => {
        // Upcoming rows are all date-only, but the run (per the server) has curtain times.
        const event = { shows: [{ date: '2026-10-01 00:00:00' }], show_summary: { curtain_times: true } };
        expect(eventUsesCurtainTimes(event)).toBe(true);
        expect(eventUsesCurtainTimes({ shows: [{ date: '2026-10-01 19:30:00' }], show_summary: { curtain_times: false } })).toBe(false);
    });

    it('falls back to inspecting the shows when there is no summary (editor, admin review)', () => {
        expect(eventUsesCurtainTimes({ shows: [{ date: '2026-10-01 19:30:00' }] })).toBe(true);
        expect(eventUsesCurtainTimes({ shows: [{ date: '2026-10-01 00:00:00' }] })).toBe(false);
        expect(eventUsesCurtainTimes(null)).toBe(false);
    });
});

describe('show history (days more than a year old, kept as weekly runs)', () => {
    // Every day from `from` to `to` on one of `weekdays`, the slow obvious way.
    const reference = (from, to, weekdays) => {
        const out = [];
        for (let d = dayjs.utc(from); !d.isAfter(dayjs.utc(to), 'day'); d = d.add(1, 'day')) {
            if (weekdays.includes(d.day())) out.push(d.format('YYYY-MM-DD'));
        }
        return out;
    };

    it('expands a run open Tuesday to Sunday since 1977', () => {
        const history = { through: '2025-09-27', runs: [{ from: '1977-10-01', to: '2025-09-27', days: [0, 2, 3, 4, 5, 6] }] };
        const days = showHistoryDays(history);

        expect(days).toEqual(reference('1977-10-01', '2025-09-27', [0, 2, 3, 4, 5, 6]));
        expect(days[0]).toBe('1977-10-01');
        expect(days).not.toContain('1977-10-03'); // a Monday
    });

    it('never moves a day across daylight saving, whatever the browser timezone', () => {
        // US DST began 2024-03-10 and ended 2024-11-03.
        const history = { runs: [{ from: '2024-03-09', to: '2024-03-11', days: [0, 1, 6] }, { from: '2024-11-02', to: '2024-11-04', days: [0, 1, 6] }] };

        expect(showHistoryDays(history)).toEqual(['2024-03-09', '2024-03-10', '2024-03-11', '2024-11-02', '2024-11-03', '2024-11-04']);
    });

    it('gives calendar Dates at local midnight', () => {
        const [date] = showHistoryDates({ runs: [{ from: '2001-05-04', to: '2001-05-04', days: [5] }] });

        expect([date.getFullYear(), date.getMonth(), date.getDate(), date.getHours()]).toEqual([2001, 4, 4, 0]);
    });

    it('tolerates no history and malformed runs', () => {
        expect(showHistoryDays(null)).toEqual([]);
        expect(showHistoryDays({})).toEqual([]);
        expect(showHistoryDays({ runs: [{ from: 'x' }, null] })).toEqual([]);
    });

    it('counts history days in the review summary and starts the range at the first one', () => {
        const event = {
            showtype: 'o',
            timezone: 'America/New_York',
            show_history: { runs: [{ from: '1990-01-01', to: '1990-01-03', days: [0, 1, 2, 3, 4, 5, 6] }] },
            shows: [{ date: '2026-10-01 16:00:00' }, { date: '2026-10-02 16:00:00' }],
        };

        expect(summarizeSchedule(event)).toEqual({ primary: 'Jan 1, 1990 - Oct 2, 2026', secondary: '5 shows' });
    });
});

describe('datesNearMonths', () => {
    const days = [];
    for (let y = 1977; y <= 2027; y++) for (let m = 0; m < 12; m++) days.push(new Date(y, m, 15));

    it('keeps only the dates around the months on screen', () => {
        const near = datesNearMonths(days, [{ month: 6, year: 2001 }]);

        expect(near.map((d) => `${d.getFullYear()}-${d.getMonth()}`)).toEqual(['2001-5', '2001-6', '2001-7', '2001-8']);
    });

    it('covers each calendar when two are paged separately, across a year end', () => {
        const near = datesNearMonths(days, [{ month: 0, year: 1990 }, { month: 11, year: 2020 }]);

        expect(near.map((d) => `${d.getFullYear()}-${d.getMonth()}`)).toEqual(['1989-11', '1990-0', '1990-1', '1990-2', '2020-10', '2020-11', '2021-0', '2021-1']);
    });

    it('hands back everything when it does not know the month', () => {
        expect(datesNearMonths(days, [{ year: 2001 }])).toBe(days);
        expect(datesNearMonths(days, [])).toBe(days);
    });
});
