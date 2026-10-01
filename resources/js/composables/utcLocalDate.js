/**
 * "Which calendar day is this stored UTC instant in the event's timezone?"
 *
 * Its own module, on the browser's Intl API, so the public event page (which
 * needs only this, via useShowDates) does not pull in moment-timezone and its
 * whole zone table (~57 KB gzipped). dateUtils.js re-exports it, and the
 * equivalence spec checks it against moment-timezone across zones and DST.
 */

const formatters = new Map();

const formatterFor = (timezone) => {
    if (!formatters.has(timezone)) {
        // en-CA formats as YYYY-MM-DD; formatToParts makes that explicit.
        formatters.set(timezone, new Intl.DateTimeFormat('en-CA', {
            timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit',
        }));
    }
    return formatters.get(timezone);
};

/**
 * @param {string} timezone - IANA timezone name
 * @returns {boolean} whether the browser knows it
 */
export const isKnownTimezone = (timezone) => {
    if (typeof timezone !== 'string' || timezone === '') return false;
    try {
        formatterFor(timezone);
        return true;
    } catch {
        return false;
    }
};

// "YYYY-MM-DD", "YYYY-MM-DD HH:mm:ss" or ISO "YYYY-MM-DDTHH:mm:ss(.ffffff)Z",
// always read as UTC (the database convention).
const UTC_DATETIME = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/;

const toUtcMillis = (dateTime) => {
    if (dateTime instanceof Date) return dateTime.getTime();
    const m = UTC_DATETIME.exec(String(dateTime));
    if (!m) return NaN;
    const [, y, mo, d, h = 0, mi = 0, s = 0] = m;
    return Date.UTC(+y, +mo - 1, +d, +h, +mi, +s);
};

/**
 * Convert a UTC datetime (as stored, "YYYY-MM-DD HH:mm:ss") into the calendar
 * date (YYYY-MM-DD) it falls on in the given timezone. An evening show whose
 * UTC instant rolls over to the next day (8 PM Los Angeles = 03:00 UTC the
 * next day) still resolves to the correct local day.
 *
 * @param {string|Date} dateTime - UTC datetime, e.g. "2026-10-03 03:00:00"
 * @param {string} timezone - IANA timezone (e.g. 'America/Los_Angeles')
 * @returns {string|null} Local calendar date in YYYY-MM-DD, or null
 */
export const utcDateTimeToLocalDate = (dateTime, timezone) => {
    if (!dateTime) return null;

    if (timezone && !isKnownTimezone(timezone)) {
        console.warn(`[dateUtils] Invalid timezone "${timezone}", falling back to UTC.`);
        timezone = 'UTC';
    }

    const millis = toUtcMillis(dateTime);
    if (Number.isNaN(millis)) return 'Invalid date';

    const parts = Object.fromEntries(
        formatterFor(timezone || 'UTC').formatToParts(new Date(millis)).map(p => [p.type, p.value]),
    );
    return `${parts.year}-${parts.month}-${parts.day}`;
};
