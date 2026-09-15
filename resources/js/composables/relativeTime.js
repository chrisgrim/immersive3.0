/**
 * "3 days ago", the way moment's fromNow() says it, without moment.
 *
 * The nav's recent-searches list imported moment-timezone (moment plus its
 * whole zone database, ~58 KB gzipped on every page) for this one string.
 * dayjs's relativeTime plugin was the obvious replacement but it rounds
 * months differently, so about one date in three hundred came out as
 * "a month ago" where the site used to say "2 months ago". This is a direct
 * port of moment's English humanize() (createDuration({from, to}) with its
 * calendar-month difference, Duration.as(), the default thresholds and the
 * `en` strings), so the output is byte-for-byte what visitors saw before.
 * tests/js/composables/relativeTime.spec.js checks it against the real moment
 * over tens of thousands of random instants.
 */

// moment: thresholds for relativeTime
const THRESHOLDS = { ss: 44, s: 45, m: 45, h: 22, d: 26, M: 11 };

// moment `en` locale
const STRINGS = {
    s: 'a few seconds',
    ss: '%d seconds',
    m: 'a minute',
    mm: '%d minutes',
    h: 'an hour',
    hh: '%d hours',
    d: 'a day',
    dd: '%d days',
    M: 'a month',
    MM: '%d months',
    y: 'a year',
    yy: '%d years',
};

const absFloor = (n) => (n < 0 ? Math.ceil(n) || 0 : Math.floor(n));
const daysToMonths = (days) => (days * 4800) / 146097;
const monthsToDays = (months) => (months * 146097) / 4800;
const isLeapYear = (year) => (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;

// moment's add(n, 'M') on a local moment: setMonth with the day of month
// clamped to the target month's length (Jan 31 + 1 month = Feb 28).
const addMonths = (date, months) => {
    const target = date.getMonth() + months;
    const modMonth = ((target % 12) + 12) % 12;
    const year = date.getFullYear() + (target - modMonth) / 12;
    const daysInMonth = modMonth === 1 ? (isLeapYear(year) ? 29 : 28) : 31 - ((modMonth % 7) % 2);
    const result = new Date(date.getTime());
    result.setMonth(target, Math.min(date.getDate(), daysInMonth));

    return result;
};

// moment's positiveMomentsDifference / momentsDifference: whole calendar
// months first, then the millisecond remainder.
const momentsDifference = (base, other) => {
    const positive = (from, to) => {
        let months = to.getMonth() - from.getMonth() + (to.getFullYear() - from.getFullYear()) * 12;
        if (addMonths(from, months) > to) {
            months -= 1;
        }

        return { months, milliseconds: to - addMonths(from, months) };
    };

    if (base < other) {
        return positive(base, other);
    }

    const flipped = positive(other, base);

    return { months: -flipped.months, milliseconds: -flipped.milliseconds };
};

/**
 * @param {Date|string|number} date - The instant to describe (an ISO string
 *   from the API, a Date, or a timestamp)
 * @param {Date} [now]
 * @returns {string} e.g. "a few seconds ago", "11 days ago", "in 2 months"
 */
export const fromNow = (date, now = new Date()) => {
    const then = date instanceof Date ? date : new Date(date);
    // moment: createDuration({ from: now, to: then })
    const { months: M, milliseconds: ms } = momentsDifference(now, then);
    const isFuture = ms + (M % 12) * 2592e6 + absFloor(M / 12) * 31536e6 > 0;

    // Duration.abs() then Duration.as(unit), _days is always 0 here
    const absMs = Math.abs(ms);
    const absMonths = Math.abs(M);
    const wholeDays = Math.round(monthsToDays(absMonths));
    const seconds = Math.round(wholeDays * 86400 + absMs / 1000);
    const minutes = Math.round(wholeDays * 1440 + absMs / 6e4);
    const hours = Math.round(wholeDays * 24 + absMs / 36e5);
    const days = Math.round(wholeDays + absMs / 864e5);
    const fractionalMonths = absMonths + daysToMonths(absMs / 864e5);
    const months = Math.round(fractionalMonths);
    const years = Math.round(fractionalMonths / 12);

    const [key, count] =
        (seconds <= THRESHOLDS.ss && ['s', seconds]) ||
        (seconds < THRESHOLDS.s && ['ss', seconds]) ||
        (minutes <= 1 && ['m']) ||
        (minutes < THRESHOLDS.m && ['mm', minutes]) ||
        (hours <= 1 && ['h']) ||
        (hours < THRESHOLDS.h && ['hh', hours]) ||
        (days <= 1 && ['d']) ||
        (days < THRESHOLDS.d && ['dd', days]) ||
        (months <= 1 && ['M']) ||
        (months < THRESHOLDS.M && ['MM', months]) ||
        (years <= 1 && ['y']) ||
        ['yy', years];

    const phrase = STRINGS[key].replace('%d', count || 1);

    return isFuture ? `in ${phrase}` : `${phrase} ago`;
};
