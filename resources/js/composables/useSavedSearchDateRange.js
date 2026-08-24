/**
 * Shared date-string <-> Date conversion for the saved-search criteria
 * schema's `start`/`end` fields ('YYYY-MM-DD 00:00:00', see
 * NormalizeSavedSearchCriteriaAction). Used by both SavedSearchesEdit.vue
 * (editing) and saved-search-card.vue (display) so they can't drift apart —
 * a date that round-trips through one and gets read by the other must
 * always agree on the exact same calendar day.
 *
 * Deliberately NOT dateUtils.js's moment-timezone helpers — those exist for
 * a different domain (an event's own show dates, resolved against that
 * event's stored timezone). A saved search's date filter has no timezone of
 * its own; it's just "which calendar day did the browsing user pick,"
 * exactly like location-search.vue's own inline date-picker already treats
 * it (see its formatForUrl) — this mirrors that logic without touching that
 * file, since the live search bar is explicitly out of scope here.
 *
 * NOT `new Date('YYYY-MM-DD 00:00:00')` for parsing: that exact string
 * shape isn't a portable ECMAScript date format (browsers are inconsistent
 * about whether/how they parse it), and even where it works, treating the
 * stored value as UTC and reading it back in a negative-UTC-offset
 * timezone shifts the displayed day backward by one. Explicit
 * year/month/day extraction avoids both problems entirely.
 */

// String -> local-midnight Date, or null for empty/malformed input.
export function parseSearchDate(value) {
    if (!value || typeof value !== 'string' || value.length < 10) return null;

    const [year, month, day] = value.slice(0, 10).split('-').map(Number);
    if (!year || !month || !day) return null;

    const date = new Date(year, month - 1, day);
    // Guards against e.g. '2026-02-30' silently rolling over to March 2nd —
    // an invalid calendar date some legacy/hand-edited row could contain,
    // which should be treated as unset rather than displayed wrong.
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return null;
    }

    return date;
}

// Date -> 'YYYY-MM-DD 00:00:00', reading the LOCAL calendar day (not the
// instant) so parseSearchDate -> formatSearchDate round-trips to the exact
// same string regardless of the browser's own timezone offset — mirrors
// location-search.vue's formatForUrl exactly (Date.UTC of the local y/m/d,
// not a raw toISOString of the Date itself, which would read UTC fields).
export function formatSearchDate(date) {
    const utcMidnight = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate(), 0, 0, 0));
    return utcMidnight.toISOString().split('T')[0] + ' 00:00:00';
}

// Only the first 10 chars ('YYYY-MM-DD') matter for "is this the same
// calendar day" — more resilient than exact string equality if a row ever
// carries a different time suffix (e.g. a hand-edited or legacy row).
export function isSameSearchDate(a, b) {
    if (!a || !b) return a === b;
    return a.slice(0, 10) === b.slice(0, 10);
}

// Short "Jun 27" / "Jun 27 – Jun 29" label, or null when there's no start —
// same no-year convention location-search.vue's own formatDate uses.
export function formatSearchDateRangeLabel(start, end) {
    const startDate = parseSearchDate(start);
    if (!startDate) return null;

    const formatOne = (date) => date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

    const endDate = parseSearchDate(end);
    if (!endDate || isSameSearchDate(start, end)) return formatOne(startDate);

    return `${formatOne(startDate)} – ${formatOne(endDate)}`;
}
