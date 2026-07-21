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
     * "9999-12-31" is rejected after ~1000 iterations rather than spinning
     * millions of times and exhausting memory. 1000 shows comfortably covers any
     * realistic run — a weekly show for ~19 years, a 4-day-a-week run for ~5
     * years, or a daily run for ~2.7 years — while firmly rejecting the absurd.
     */
    public const MAX_OCCURRENCES = 1000;

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
