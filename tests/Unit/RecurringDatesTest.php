<?php

use App\Support\RecurringDates;
use Carbon\Carbon;

/**
 * Independent day-by-day reference implementation. RecurringDates::expand walks
 * weekly per weekday; this walks every day and filters. They must agree — a
 * parity check that would catch an off-by-one, a wrong inclusive bound, or a
 * weekday mix-up without either side sharing the bug.
 *
 * @param  array<int, int>  $days
 * @return array<int, string>
 */
function referenceRecurring(array $days, string $start, string $end, string $tz): array
{
    $s = Carbon::parse($start, 'UTC')->setTimezone($tz)->setTime(12, 0, 0);
    $e = Carbon::parse($end, 'UTC')->setTimezone($tz)->setTime(12, 0, 0);

    $out = [];
    for ($d = $s->copy(); $d->lessThanOrEqualTo($e); $d->addDay()) {
        if (in_array($d->dayOfWeek, $days, true)) {
            $out[] = $d->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
        }
    }
    sort($out);

    return array_values(array_unique($out));
}

it('expands a Wed–Sat run to the exact noon-UTC datetimes', function () {
    // Kathryn's pattern, verified by hand: Jun 18(Thu) 19 20, then 24(Wed) 25 26 27,
    // all at noon America/Toronto = 16:00 UTC (EDT). Jun 21–23 and 28 are excluded.
    $dates = RecurringDates::expand([3, 4, 5, 6], '2026-06-18 16:00:00', '2026-06-28 16:00:00', 'America/Toronto');

    expect($dates)->toBe([
        '2026-06-18 16:00:00',
        '2026-06-19 16:00:00',
        '2026-06-20 16:00:00',
        '2026-06-24 16:00:00',
        '2026-06-25 16:00:00',
        '2026-06-26 16:00:00',
        '2026-06-27 16:00:00',
    ]);
});

it('matches the day-by-day reference across several patterns', function (array $days, string $start, string $end, string $tz) {
    expect(RecurringDates::expand($days, $start, $end, $tz))
        ->toBe(referenceRecurring($days, $start, $end, $tz));
})->with([
    'single weekday, one month' => [[1], '2026-03-01 12:00:00', '2026-03-31 12:00:00', 'UTC'],
    'wed–sat, five months (Toronto)' => [[3, 4, 5, 6], '2026-06-18 16:00:00', '2026-11-29 17:00:00', 'America/Toronto'],
    'thu–mon wrap-around week' => [[4, 5, 6, 0, 1], '2026-03-05 12:00:00', '2026-07-25 12:00:00', 'America/Los_Angeles'],
    'all seven days' => [[0, 1, 2, 3, 4, 5, 6], '2026-01-01 12:00:00', '2026-02-15 12:00:00', 'Europe/London'],
    'start already on the weekday' => [[4], '2026-06-18 16:00:00', '2026-07-16 16:00:00', 'America/Toronto'],
    'across an autumn DST change' => [[3], '2026-10-14 16:00:00', '2026-12-09 17:00:00', 'America/Toronto'],
]);

it('anchors every occurrence at noon in the event timezone', function () {
    $tz = 'America/Toronto';
    foreach (RecurringDates::expand([3, 4, 5, 6], '2026-06-18 16:00:00', '2026-11-29 17:00:00', $tz) as $utc) {
        $local = Carbon::parse($utc, 'UTC')->setTimezone($tz);
        expect($local->format('H:i:s'))->toBe('12:00:00');           // always local noon
        expect(in_array($local->dayOfWeek, [3, 4, 5, 6], true))->toBeTrue(); // always a chosen weekday
    }
});

it('shifts the UTC offset across a DST boundary while keeping local noon', function () {
    // Toronto is EDT (UTC-4 → 16:00) before Nov 1 2026 and EST (UTC-5 → 17:00) after.
    $dates = RecurringDates::expand([3], '2026-10-21 16:00:00', '2026-11-11 17:00:00', 'America/Toronto');

    expect($dates)->toBe([
        '2026-10-21 16:00:00', // EDT
        '2026-10-28 16:00:00', // EDT
        '2026-11-04 17:00:00', // EST (clocks fell back Nov 1)
        '2026-11-11 17:00:00', // EST
    ]);
});

it('includes the end date when it lands on a chosen weekday', function () {
    // Jul 16 2026 is a Thursday; it is the inclusive end and must appear.
    $dates = RecurringDates::expand([4], '2026-07-02 16:00:00', '2026-07-16 16:00:00', 'America/Toronto');
    expect($dates)->toBe(['2026-07-02 16:00:00', '2026-07-09 16:00:00', '2026-07-16 16:00:00']);
});

it('returns nothing when the weekday never falls in the range', function () {
    // A Mon–Wed span that contains no Saturday (day 6).
    expect(RecurringDates::expand([6], '2026-06-15 12:00:00', '2026-06-17 12:00:00', 'UTC'))->toBe([]);
});

it('returns nothing for an empty weekday list or an inverted range', function () {
    expect(RecurringDates::expand([], '2026-06-01 12:00:00', '2026-06-30 12:00:00', 'UTC'))->toBe([]);
    expect(RecurringDates::expand([1], '2026-06-30 12:00:00', '2026-06-01 12:00:00', 'UTC'))->toBe([]);
});

it('returns a sorted, de-duplicated list even with duplicate/unsorted weekdays', function () {
    $dates = RecurringDates::expand([5, 1, 1, 5], '2026-06-01 12:00:00', '2026-06-30 12:00:00', 'UTC');
    $sorted = $dates;
    sort($sorted);
    expect($dates)->toBe($sorted)
        ->and($dates)->toBe(array_values(array_unique($dates)));
});

it('rejects a recurrence that would exceed the occurrence cap', function () {
    // Daily for ~4 years is ~1460 shows — over the 1000-show cap.
    expect(fn () => RecurringDates::expand([0, 1, 2, 3, 4, 5, 6], '2026-01-01 12:00:00', '2030-01-01 12:00:00', 'UTC'))
        ->toThrow(RangeException::class);
});

it('allows a large recurrence up to the cap and stays bounded', function () {
    // Daily for two years is ~730 shows — under the cap, no throw.
    $dates = RecurringDates::expand([0, 1, 2, 3, 4, 5, 6], '2026-01-01 12:00:00', '2027-12-31 12:00:00', 'UTC');
    expect(count($dates))->toBeGreaterThan(700)
        ->and(count($dates))->toBeLessThanOrEqual(RecurringDates::MAX_OCCURRENCES);
});

it('accepts exactly the cap and rejects one over it', function () {
    $start = '2026-01-01 12:00:00';
    // All 7 weekdays ⇒ one occurrence per calendar day. An inclusive span of
    // exactly MAX_OCCURRENCES days is allowed; one more day throws.
    $endAtCap = Carbon::parse($start, 'UTC')->addDays(RecurringDates::MAX_OCCURRENCES - 1)->format('Y-m-d H:i:s');
    $endOverCap = Carbon::parse($start, 'UTC')->addDays(RecurringDates::MAX_OCCURRENCES)->format('Y-m-d H:i:s');

    expect(RecurringDates::expand([0, 1, 2, 3, 4, 5, 6], $start, $endAtCap, 'UTC'))
        ->toHaveCount(RecurringDates::MAX_OCCURRENCES);

    expect(fn () => RecurringDates::expand([0, 1, 2, 3, 4, 5, 6], $start, $endOverCap, 'UTC'))
        ->toThrow(RangeException::class);
});
