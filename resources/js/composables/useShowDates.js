/**
 * A show's calendar day, in the EVENT's timezone. shows.date is a UTC
 * "YYYY-MM-DD HH:mm:ss"; the readers here are the twin of Event::localDate()
 * and Show::localDay() in PHP, which document the rule. Pure functions,
 * shared by every component that turns a show into a day.
 */
import dayjs from 'dayjs';
import { utcDateTimeToLocalDate } from './dateUtils';

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
