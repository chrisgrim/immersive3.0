<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The compact form of an event's old show days, stored in events.show_history.
 *
 * A permanent artwork open every day since 1977 has about 18,000 show days,
 * far more than the shows table can hold per event (RecurringDates::MAX_OCCURRENCES,
 * set by Elasticsearch's nested-object limit). Days older than a cutoff are
 * therefore kept here as weekly runs instead of one row each, and only the
 * recent and upcoming days stay rows.
 *
 * Shape:
 *   {
 *     "through": "2025-09-27",
 *     "runs": [
 *       {"from": "1977-10-01", "to": "2025-09-27", "days": [0, 2, 3, 4, 5, 6]}
 *     ]
 *   }
 *
 * Every day is a calendar day in the event's own timezone (the day it played,
 * Show::localDay), never a UTC instant. A run means: every day from `from` to
 * `to` (inclusive) whose weekday (0 = Sunday ... 6 = Saturday, as in
 * RecurringDates) is in `days` was a show day, and no other day in that span
 * was. Runs are sorted and never overlap. `through` is the last day this
 * history speaks for: every show day on or before it is in the runs, and the
 * shows table holds only days after it.
 *
 * The encoding is lossless: pack() then days() gives back exactly the days put
 * in. A closure (a holiday, a season off) simply ends one run and starts the
 * next, so a real daily run over decades is a few hundred runs at most.
 */
class ShowHistory
{
    /**
     * Pack sorted-or-not local days into runs. Days after $through are refused
     * rather than silently dropped: the caller decides which days are old.
     *
     * @param  iterable<int, string>  $days  local days, "Y-m-d"
     * @return array{through: string, runs: array<int, array{from: string, to: string, days: array<int, int>}>}
     */
    public static function pack(iterable $days, string $through): array
    {
        $through = self::day($through);

        $set = [];
        foreach ($days as $day) {
            $day = self::day($day);
            if ($day > $through) {
                throw new \InvalidArgumentException("Show day {$day} is after the history's end {$through}.");
            }
            $set[$day] = true;
        }
        ksort($set);
        $sorted = array_keys($set);

        $runs = [];
        $i = 0;
        $n = count($sorted);

        while ($i < $n) {
            $from = CarbonImmutable::parse($sorted[$i], 'UTC');

            // The weekdays this run repeats on: whatever played in its first
            // seven days. The walk below checks every later day against them.
            $weekdays = [];
            $windowEnd = $from->addDays(6)->toDateString();
            for ($j = $i; $j < $n && $sorted[$j] <= $windowEnd; $j++) {
                $weekdays[CarbonImmutable::parse($sorted[$j], 'UTC')->dayOfWeek] = true;
            }

            // Walk day by day while "is a show day" matches "falls on one of
            // the run's weekdays". The first disagreement ends the run at the
            // last show day before it; that is what keeps the packing exact.
            $to = $from;
            $j = $i;
            $cursor = $from;
            $last = $sorted[$n - 1];
            while ($cursor->toDateString() <= $last) {
                $key = $cursor->toDateString();
                $isShow = $j < $n && $sorted[$j] === $key;
                if ($isShow !== isset($weekdays[$cursor->dayOfWeek])) {
                    break;
                }
                if ($isShow) {
                    $to = $cursor;
                    $j++;
                }
                $cursor = $cursor->addDay();
            }

            // Keep only weekdays that really occur between from and to, so a
            // short run never claims a weekday it does not contain.
            $days = [];
            foreach (array_keys($weekdays) as $weekday) {
                $first = $from->addDays(($weekday - $from->dayOfWeek + 7) % 7);
                if ($first->lessThanOrEqualTo($to)) {
                    $days[] = $weekday;
                }
            }
            sort($days);

            $runs[] = ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days];
            $i = $j;
        }

        return ['through' => $through, 'runs' => $runs];
    }

    /**
     * Every show day in a history, oldest first.
     *
     * @param  array|null  $history  the events.show_history value
     * @return array<int, string>
     */
    public static function days(?array $history): array
    {
        $out = [];
        foreach (self::runs($history) as $run) {
            $weekdays = array_flip($run['days']);
            $to = $run['to'];
            for ($d = CarbonImmutable::parse($run['from'], 'UTC'); $d->toDateString() <= $to; $d = $d->addDay()) {
                if (isset($weekdays[$d->dayOfWeek])) {
                    $out[] = $d->toDateString();
                }
            }
        }

        return $out;
    }

    /**
     * How many show days a history holds, without listing them.
     */
    public static function count(?array $history): int
    {
        $total = 0;
        foreach (self::runs($history) as $run) {
            $from = CarbonImmutable::parse($run['from'], 'UTC');
            $span = (int) $from->diffInDays(CarbonImmutable::parse($run['to'], 'UTC')) + 1;
            foreach ($run['days'] as $weekday) {
                $offset = ($weekday - $from->dayOfWeek + 7) % 7;
                if ($offset < $span) {
                    $total += intdiv($span - 1 - $offset, 7) + 1;
                }
            }
        }

        return $total;
    }

    /**
     * The oldest show day in a history, or null when it holds none.
     */
    public static function firstDay(?array $history): ?string
    {
        $runs = self::runs($history);

        return $runs === [] ? null : $runs[0]['from'];
    }

    /**
     * The newest show day in a history, or null when it holds none.
     */
    public static function lastDay(?array $history): ?string
    {
        $runs = self::runs($history);

        return $runs === [] ? null : $runs[count($runs) - 1]['to'];
    }

    /**
     * The runs of a stored history, tolerating null and an empty value.
     *
     * @return array<int, array{from: string, to: string, days: array<int, int>}>
     */
    private static function runs(?array $history): array
    {
        return array_values($history['runs'] ?? []);
    }

    /**
     * A day as a strict "Y-m-d", so string comparison orders days correctly.
     */
    private static function day(string $day): string
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', substr($day, 0, 10), 'UTC');
        if ($parsed === false || $parsed->toDateString() !== substr($day, 0, 10)) {
            throw new \InvalidArgumentException("Not a calendar day: {$day}");
        }

        return $parsed->toDateString();
    }
}
