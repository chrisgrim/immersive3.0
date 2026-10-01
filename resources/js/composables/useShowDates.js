/**
 * A show's calendar day, in the EVENT's timezone. shows.date is a UTC
 * "YYYY-MM-DD HH:mm:ss"; the readers here are the twin of Event::localDate()
 * and Show::localDay() in PHP, which document the rule. Pure functions,
 * shared by every component that turns a show into a day.
 */
import dayjs from 'dayjs';
import { utcDateTimeToLocalDate } from './utcLocalDate';

/**
 * Whether a schedule's stored rows record real times of day.
 *
 * A row at exactly 00:00:00 UTC is a calendar DATE — the wizard wrote every
 * show that way until late 2025, and assistants still send midnight for a
 * list of dates — UNLESS the schedule also has rows at other times, which
 * means whoever wrote it was recording curtain times (8 PM Eastern is 00:00
 * UTC). Same rule as Show::usesCurtainTimes() in PHP.
 */
export const usesCurtainTimes = (shows) =>
    Array.isArray(shows) && shows.some((show) => {
        const date = typeof show === 'string' ? show : show?.date;
        return typeof date === 'string' && date.length >= 19 && date.slice(11, 19) !== '00:00:00';
    });

/**
 * Whether an EVENT's schedule uses curtain times. The event page embeds only
 * the upcoming rows, so the server also sends the whole-run answer in
 * show_summary.curtain_times; a full schedule (the editor, the admin review)
 * is inspected directly.
 */
export const eventUsesCurtainTimes = (event) =>
    typeof event?.show_summary?.curtain_times === 'boolean'
        ? event.show_summary.curtain_times
        : usesCurtainTimes(event?.shows);

const isDateOnly = (utcDateTime, curtainTimes) =>
    !curtainTimes && typeof utcDateTime === 'string' && utcDateTime.endsWith('00:00:00');

/**
 * 'YYYY-MM-DD' in the event's timezone, or null. `curtainTimes` is
 * usesCurtainTimes(event.shows) for a stored row; leave it false for a value
 * that is not a stored row, which makes a midnight value mean "this date".
 */
export const showDay = (utcDateTime, timezone, curtainTimes = false) => {
    if (isDateOnly(utcDateTime, curtainTimes)) return utcDateTime.slice(0, 10);
    return utcDateTimeToLocalDate(utcDateTime, timezone);
};

/**
 * That day as a Date at local midnight — what VueDatePicker highlighting
 * and a "still upcoming?" comparison against today need. The browser's
 * timezone only decides where midnight is; the day itself is the event's.
 */
export const showDayAsDate = (utcDateTime, timezone, curtainTimes = false) => {
    const day = showDay(utcDateTime, timezone, curtainTimes);
    if (!day) return null;
    const [year, month, date] = day.split('-').map(Number);
    return new Date(year, month - 1, date);
};

/** The day formatted for display, default "Oct 31, 2026". Empty for nothing. */
export const formatShowDay = (utcDateTime, timezone, format = 'MMM D, YYYY', curtainTimes = false) => {
    const day = showDay(utcDateTime, timezone, curtainTimes);
    return day ? dayjs(day).format(format) : '';
};

/** Whether the show's day is today or later (today in the browser's clock). */
export const isShowUpcoming = (utcDateTime, timezone, now = new Date(), curtainTimes = false) => {
    const day = showDayAsDate(utcDateTime, timezone, curtainTimes);
    if (!day) return false;
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    return day >= today;
};

/**
 * Every show day in an event's compact show history (event.show_history),
 * oldest first, as 'YYYY-MM-DD' in the event's timezone. Days more than a
 * year old are kept there as weekly runs instead of one row each; a run is
 * every day from `from` to `to` whose weekday (0 = Sunday) is in `days`.
 * The twin of App\Support\ShowHistory::days() in PHP. Calendar arithmetic
 * only (UTC dates), so the browser's timezone and DST never move a day.
 */
export const showHistoryDays = (history) => {
    const out = [];
    const runs = Array.isArray(history?.runs) ? history.runs : [];
    const pad = (n) => String(n).padStart(2, '0');

    runs.forEach((run) => {
        if (typeof run?.from !== 'string' || typeof run?.to !== 'string' || !Array.isArray(run?.days)) return;
        const weekdays = new Set(run.days.map(Number));
        const [fy, fm, fd] = run.from.split('-').map(Number);
        const [ty, tm, td] = run.to.split('-').map(Number);
        const end = Date.UTC(ty, tm - 1, td);
        if (Number.isNaN(end)) return;

        for (let t = Date.UTC(fy, fm - 1, fd); t <= end; t += 86400000) {
            const d = new Date(t);
            if (weekdays.has(d.getUTCDay())) {
                out.push(`${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}`);
            }
        }
    });

    return out;
};

/**
 * The history's days as Dates at local midnight, for VueDatePicker
 * highlighting (same shape as showDayAsDate()).
 */
export const showHistoryDates = (history) =>
    showHistoryDays(history).map((day) => {
        const [year, month, date] = day.split('-').map(Number);
        return new Date(year, month - 1, date);
    });

/**
 * Only the dates a calendar can show right now: those within `before` months
 * before and `after` months after any of the months on screen (`months`, a
 * list of { month (0-11), year }). VueDatePicker checks every day cell
 * against every date it is given, so handing it a whole run (tens of
 * thousands of days for a permanent artwork) made each month change take
 * seconds. Filtering here is one cheap pass.
 */
export const datesNearMonths = (dates, months, before = 1, after = 2) => {
    const windows = (months ?? [])
        .filter((m) => Number.isInteger(m?.month) && Number.isInteger(m?.year))
        .map(({ month, year }) => [new Date(year, month - before, 1), new Date(year, month + after + 1, 1)]);
    if (!windows.length) return dates;

    return dates.filter((date) => windows.some(([start, end]) => date >= start && date < end));
};

/**
 * A schedule's span, "Oct 31, 2026" for one day or "Oct 31, 2026 - Nov 4,
 * 2026" across several. "No dates set" when there is nothing to show.
 */
export const NO_DATES = 'No dates set';

export const formatShowDayRange = (shows, timezone) => {
    if (!shows?.length) return NO_DATES;

    const curtainTimes = usesCurtainTimes(shows);
    const days = shows.map((show) => showDay(show.date, timezone, curtainTimes)).filter(Boolean).sort();
    if (!days.length) return NO_DATES;

    const first = dayjs(days[0]).format('MMM D, YYYY');
    const last = dayjs(days[days.length - 1]).format('MMM D, YYYY');

    return first === last ? first : `${first} - ${last}`;
};

/**
 * The sentinel show types have no performances: their ONE stored row IS the
 * listing's end date. Show::targetDatesFor() in PHP returns a single-element
 * array for 'a' (always available) and 'l' (the retired limited type), and
 * closingDate comes from always_config rather than from the rows.
 *
 * So rendering that row the way a real schedule is rendered — a date plus a
 * show count — states the opposite of the truth: "Mar 7, 2027 / 1 show" reads
 * as "this happens once, on that date" when it means "available every day
 * until then". Both review screens (the approval queue's EventReview.vue and
 * the wizard's own review.vue) call this so they cannot drift apart.
 *
 * @returns {{primary: string, secondary: string}} the two lines to render
 */
const SENTINEL_SHOWTYPES = ['a', 'l'];

export const summarizeSchedule = (event) => {
    const shows = event?.shows ?? [];
    const showtype = event?.showtype;

    if (SENTINEL_SHOWTYPES.includes(showtype)) {
        // A row that carries no readable date is the same as having no row:
        // ask the formatter rather than trusting shows.length, or a malformed
        // sentinel reads "Searchable until No dates set".
        const endDate = formatShowDayRange(shows, event?.timezone);

        return {
            primary: showtype === 'a' ? 'Always available' : 'Limited run',
            secondary: endDate === NO_DATES ? 'No end date set' : `Searchable until ${endDate}`,
        };
    }

    // Days more than a year old are in the compact show history, not rows:
    // they start the range and count towards the total.
    const historyDays = showHistoryDays(event?.show_history);
    const total = shows.length + historyDays.length;
    let primary = formatShowDayRange(shows, event?.timezone);
    if (historyDays.length) {
        const first = dayjs(historyDays[0]).format('MMM D, YYYY');
        const last = primary === NO_DATES ? dayjs(historyDays[historyDays.length - 1]).format('MMM D, YYYY') : primary.split(' - ').pop();
        primary = first === last ? first : `${first} - ${last}`;
    }

    return {
        primary,
        secondary: `${total} show${total !== 1 ? 's' : ''}`,
    };
};
