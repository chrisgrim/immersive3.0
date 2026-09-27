<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Server-side expansion of a weekly recurring schedule into concrete show
 * datetimes — the PHP counterpart of the frontend's generateRecurringDates()
 * (resources/js/composables/dateUtils.js).
 *
 * It is kept behaviourally identical to that function so the web wizard (which
 * expands client-side) and the MCP/assistant (which can now expand server-side)
 * produce the SAME shows for the same recipe: one occurrence per matching
 * weekday, anchored at NOON in the event timezone, emitted as UTC "Y-m-d H:i:s"
 * — the format the rest of the pipeline (dateArray, the past-date guard,
 * Show::saveShows) already speaks.
 *
 * Letting the server expand means a caller can send just the recipe
 * (startDate/endDate/daysOfWeek) instead of enumerating every date. That is
 * faster, cheaper, and removes a whole class of "the model miscounted the
 * dates" bugs — deterministic date math beats an LLM listing 100 days by hand.
 */
class RecurringDates
{
    /**
     * Hard ceiling on the number of shows a single recurrence may produce, so a
     * tiny recipe can never amplify into an unbounded schedule. It is enforced
     * inside the build loop, so a far-future (or wrong-year) endDate like
     * "9999-12-31" is rejected after a few thousand iterations rather than
     * spinning millions of times and exhausting memory.
     *
     * This is a runaway guard, NOT a policy limit on how long a run may be — the
     * web wizard applies no occurrence cap at all, and an MCP caller that
     * enumerates dateArray by hand isn't capped either. So it is sized to sit
     * ABOVE anything staff can build: moderators and admins can reach
     * Show::STAFF_LOOKBACK_YEARS (20) back and 12 months ahead in the wizard,
     * and a 7-day-a-week run across ~21 years is ~7,700 shows. 20,000 clears
     * that with headroom while still rejecting the absurd.
     *
     * It was 1,000, then 4,000; both rejected real runs. Permanent artworks
     * open daily since the early 2010s (a Kusama room, a Turrell) need well
     * over 4,000 dates. At 20,000 a daily run fits ~54 years.
     */
    public const MAX_OCCURRENCES = 20000;

    /**
     * Expand a weekly recurrence into its concrete show datetimes.
     *
     * @param  array<int, int|string>  $daysOfWeek  weekdays to include, 0=Sunday … 6=Saturday
     * @param  string  $startDate  first eligible day, UTC "Y-m-d H:i:s"
     * @param  string  $endDate  last eligible day (inclusive), UTC "Y-m-d H:i:s"
     * @param  string  $timezone  IANA timezone the shows are anchored in
     * @return array<int, string> sorted, de-duplicated UTC "Y-m-d H:i:s" datetimes
     *
     * @throws \RangeException when the recurrence would produce more than MAX_OCCURRENCES shows
     */
    public static function expand(array $daysOfWeek, string $startDate, string $endDate, string $timezone): array
    {
        // Anchor both bounds at noon on their calendar day in the event timezone,
        // mirroring moment.tz(date, tz).hour(12) on the frontend. Parsing the
        // incoming UTC datetime and shifting into the event tz recovers the
        // caller's intended calendar day regardless of the wall-clock time sent.
        $start = Carbon::parse($startDate, 'UTC')->setTimezone($timezone)->setTime(12, 0, 0);
        $end = Carbon::parse($endDate, 'UTC')->setTimezone($timezone)->setTime(12, 0, 0);

        $dates = [];

        foreach (array_unique(array_map('intval', $daysOfWeek)) as $dayIndex) {
            if ($dayIndex < 0 || $dayIndex > 6) {
                continue; // ignore out-of-range weekday indexes
            }

            $current = $start->copy();

            // Walk forward to the first occurrence of this weekday in the range.
            while ($current->dayOfWeek !== $dayIndex && $current->lessThan($end)) {
                $current->addDay();
            }

            // The weekday may never fall inside the range (e.g. a one-week span
            // that doesn't contain it) — emit nothing for it then.
            if ($current->dayOfWeek !== $dayIndex) {
                continue;
            }

            // Weekly occurrences through the inclusive end. Adding a week keeps
            // the local noon anchor, so the UTC offset (and thus the emitted UTC
            // time) shifts correctly across a DST boundary — matching the wizard.
            while ($current->lessThanOrEqualTo($end)) {
                $dates[] = $current->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');

                // Enforce the ceiling as we build — this is also what stops a
                // runaway (e.g. a year-9999 endDate) from ballooning the loop.
                if (count($dates) > self::MAX_OCCURRENCES) {
                    throw new \RangeException('Recurring schedule exceeds the limit of '.self::MAX_OCCURRENCES.' shows.');
                }

                $current->addWeek();
            }
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }
}
