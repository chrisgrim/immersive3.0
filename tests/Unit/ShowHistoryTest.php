<?php

use App\Support\ShowHistory;
use Carbon\CarbonImmutable;

/**
 * Every day from $from to $to (inclusive) on one of $weekdays, 0 = Sunday.
 *
 * @param  array<int, int>  $weekdays
 * @return array<int, string>
 */
function historyDays(string $from, string $to, array $weekdays): array
{
    $out = [];
    for ($d = CarbonImmutable::parse($from, 'UTC'); $d->toDateString() <= $to; $d = $d->addDay()) {
        if (in_array($d->dayOfWeek, $weekdays, true)) {
            $out[] = $d->toDateString();
        }
    }

    return $out;
}

/**
 * Pack, unpack, and check every reader agrees with the days put in.
 *
 * @param  array<int, string>  $days
 */
function assertRoundTrip(array $days, string $through): array
{
    $history = ShowHistory::pack($days, $through);

    $expected = array_values(array_unique($days));
    sort($expected);

    expect(ShowHistory::days($history))->toBe($expected)
        ->and(ShowHistory::count($history))->toBe(count($expected))
        ->and(ShowHistory::firstDay($history))->toBe($expected[0] ?? null)
        ->and(ShowHistory::lastDay($history))->toBe($expected === [] ? null : end($expected))
        ->and($history['through'])->toBe($through);

    // Survives the trip through the JSON column.
    $stored = json_decode(json_encode($history), true);
    expect(ShowHistory::days($stored))->toBe($expected);

    return $history;
}

it('packs a Tuesday-to-Sunday run since 1977 into one run', function () {
    $days = historyDays('1977-10-01', '2025-09-27', [0, 2, 3, 4, 5, 6]);

    $history = assertRoundTrip($days, '2025-09-27');

    expect(count($days))->toBeGreaterThan(14000)
        ->and($history['runs'])->toBe([
            ['from' => '1977-10-01', 'to' => '2025-09-27', 'days' => [0, 2, 3, 4, 5, 6]],
        ]);
});

it('splits a run around a closure and stays exact', function () {
    $days = historyDays('2000-01-01', '2010-12-31', [0, 1, 2, 3, 4, 5, 6]);
    // Closed every Christmas and New Year's Day, and for all of 2005.
    $days = array_values(array_filter($days, fn ($d) => ! str_ends_with($d, '-12-25')
        && ! str_ends_with($d, '-01-01')
        && ! str_starts_with($d, '2005-')));

    $history = assertRoundTrip($days, '2011-06-30');

    expect(count($history['runs']))->toBeLessThan(25);
});

it('handles a changed weekly pattern', function () {
    $days = array_merge(
        historyDays('2015-03-04', '2018-06-30', [3, 4, 5, 6]),
        historyDays('2018-07-01', '2022-02-27', [0, 5, 6]),
    );

    $history = assertRoundTrip($days, '2022-02-27');

    expect(count($history['runs']))->toBeLessThanOrEqual(3);
});

it('handles scattered specific dates', function () {
    assertRoundTrip(['2019-05-02', '2019-05-03', '2019-06-15', '2020-02-29', '2021-11-11'], '2021-12-31');
});

it('handles a single day, duplicates and unsorted input', function () {
    assertRoundTrip(['2012-02-29'], '2012-02-29');
    assertRoundTrip(['2020-03-10', '2020-03-01', '2020-03-10', '2020-03-05'], '2020-03-31');
});

it('holds nothing when given no days', function () {
    $history = ShowHistory::pack([], '2025-01-01');

    expect($history)->toBe(['through' => '2025-01-01', 'runs' => []])
        ->and(ShowHistory::days($history))->toBe([])
        ->and(ShowHistory::count($history))->toBe(0)
        ->and(ShowHistory::firstDay($history))->toBeNull()
        ->and(ShowHistory::days(null))->toBe([])
        ->and(ShowHistory::count(null))->toBe(0);
});

it('never claims a weekday a short run does not reach', function () {
    // Monday and Tuesday only: the run is two days long.
    $history = assertRoundTrip(['2024-01-01', '2024-01-02'], '2024-01-31');

    expect($history['runs'][0]['days'])->toBe([1, 2]);
});

it('refuses a day after the history end, and a malformed day', function () {
    expect(fn () => ShowHistory::pack(['2025-01-02'], '2025-01-01'))->toThrow(InvalidArgumentException::class);
    expect(fn () => ShowHistory::pack(['2025-02-30'], '2025-12-31'))->toThrow(InvalidArgumentException::class);
    expect(fn () => ShowHistory::pack(['not a day'], '2025-12-31'))->toThrow(InvalidArgumentException::class);
});

it('reads a UTC datetime string as its date part', function () {
    // Callers pass local days, but a stray "Y-m-d H:i:s" must not break it.
    assertRoundTrip(['2023-04-01'], '2023-04-30');
    expect(ShowHistory::days(ShowHistory::pack(['2023-04-01 12:00:00'], '2023-04-30')))->toBe(['2023-04-01']);
});

it('round-trips random schedules exactly', function () {
    mt_srand(20260928);

    for ($trial = 0; $trial < 300; $trial++) {
        $start = CarbonImmutable::parse('1970-01-01', 'UTC')->addDays(mt_rand(0, 20000));
        $length = mt_rand(1, 1500);
        $weekdays = [];
        foreach (range(0, 6) as $w) {
            if (mt_rand(0, 1)) {
                $weekdays[] = $w;
            }
        }
        $days = historyDays($start->toDateString(), $start->addDays($length)->toDateString(), $weekdays ?: [mt_rand(0, 6)]);

        // Knock out some days and add some strays.
        $days = array_values(array_filter($days, fn () => mt_rand(0, 99) >= 3));
        for ($k = mt_rand(0, 5); $k > 0; $k--) {
            $days[] = $start->addDays(mt_rand(0, $length))->toDateString();
        }
        if ($days === []) {
            continue;
        }

        $through = max($days);
        assertRoundTrip($days, $through);
    }
});
