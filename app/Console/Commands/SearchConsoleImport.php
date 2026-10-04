<?php

namespace App\Console\Commands;

use App\Actions\Analytics\SearchConsoleReport;
use App\Support\Google\SearchConsole;
use App\Support\Google\SearchConsoleException;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Copies Google Search Console's daily totals into search_console_daily:
 * for each day, the whole-site totals and the totals by search, page,
 * country, device and search-and-page pair. Google's data settles after
 * 2 to 3 days (its final numbers can take longer), so the nightly run
 * re-reads the last 10 days ending 2 days ago, which also fills any day a
 * slow Google or a missed night left out; --from backfills (Google keeps 16 months).
 *
 * Idempotent: a day's rows are deleted and inserted again in one
 * transaction. Each month a run stored a day in is then rebuilt in
 * search_console_monthly (what long ranges read); --rebuild-months refills
 * every month from the daily rows without calling Google. A day that fails is reported and skipped (its old rows stay);
 * a failure every day would share (no access, a refused key, Google still
 * unavailable after every retry) is reported once and stops the run.
 * Does nothing while Search Console is not configured.
 */
class SearchConsoleImport extends Command
{
    protected $signature = 'ei:search-console-import
        {--from= : First day (Y-m-d), as far back as 16 months}
        {--to= : Last day (default 2 days ago)}
        {--days= : How many days ending --to (default 10)}
        {--rebuild-months : Only rebuild the monthly totals from the daily rows (no call to Google)}';

    protected $description = 'Import Google Search Console totals (searches, pages, clicks, impressions, position) per day.';

    /** Dimension name => the API's dimensions for it. */
    public const DIMENSIONS = [
        'all' => [],
        'query' => ['query'],
        'page' => ['page'],
        'country' => ['country'],
        'device' => ['device'],
        'query_page' => ['query', 'page'],
    ];

    /** Rows per API call (Google's maximum), and per dimension and day. */
    public const PAGE_ROWS = 25000;

    public const MAX_ROWS = 50000;

    /** Each side of a query_page key, so both fit 191 (94 + ' > ' + 94), as analytics edges do. */
    public const PAIR_SIDE = 94;

    /** A pause between calls: Google allows ~1,200 a minute; a backfill makes ~3,000. */
    private const PAUSE_MS = 250;

    private ?array $countries = null;

    public function handle(SearchConsole $console): int
    {
        if ($this->option('rebuild-months')) {
            return $this->rebuildAllMonths();
        }

        if (! SearchConsole::configured()) {
            $this->warn('Search Console is not configured (SEARCH_CONSOLE_CREDENTIALS must point to a readable key file and SEARCH_CONSOLE_SITE_URL be set). Nothing imported.');

            return self::SUCCESS;
        }

        $days = $this->days();
        if ($days === null) {
            return self::FAILURE;
        }

        // A manual backfill and the nightly run never write at once.
        $lock = Cache::lock('search-console:import', 6 * 3600);
        if (! $lock->get()) {
            $this->warn('Another Search Console import is running; skipped.');

            return self::SUCCESS;
        }

        try {
            return $this->import($console, $days);
        } finally {
            $lock->release();
        }
    }

    /** @param CarbonImmutable[] $days */
    private function import(SearchConsole $console, array $days): int
    {
        $touched = [];

        try {
            return $this->importDays($console, $days, $touched);
        } finally {
            // Every month a stored day belongs to, once, even when the run
            // stopped early, so the monthly totals never miss a stored day.
            foreach (array_keys($touched) as $month) {
                try {
                    $this->rebuildMonth(CarbonImmutable::parse($month));
                } catch (Throwable $e) {
                    report($e);
                    $this->error("Month {$month}: {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * @param  CarbonImmutable[]  $days
     * @param  array<string, true>  $touched  months (Y-m-01) with a stored day
     */
    private function importDays(SearchConsole $console, array $days, array &$touched): int
    {
        $failed = false;

        foreach ($days as $day) {
            try {
                $rows = $this->fetchDay($console, $day);
                if ($rows === null) {
                    $this->line("{$day->toDateString()}: no data from Google yet.");

                    continue;
                }
                $this->store($day, $rows);
                $touched[$day->startOfMonth()->toDateString()] = true;
                $this->info("{$day->toDateString()}: ".count($rows).' rows.');
            } catch (SearchConsoleException $e) {
                report($e);
                $this->error("{$day->toDateString()}: {$e->getMessage()}");
                $failed = true;
                if ($e->fatal()) {
                    $this->error('Stopped: Google is refusing access or unavailable, so the other days would fail the same way. Run again later.');

                    return self::FAILURE;
                }
            } catch (Throwable $e) {
                // One bad day must not stop the others.
                report($e);
                $this->error("{$day->toDateString()}: {$e->getMessage()}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return CarbonImmutable[]|null */
    private function days(): ?array
    {
        $today = CarbonImmutable::today('UTC');
        $earliest = $today->subMonthsNoOverflow(16);

        try {
            $to = $this->option('to') ? CarbonImmutable::parse($this->option('to'), 'UTC')->startOfDay() : $today->subDays(2);
            $from = $this->option('from')
                ? CarbonImmutable::parse($this->option('from'), 'UTC')->startOfDay()
                : $to->subDays(max(1, (int) ($this->option('days') ?: 10)) - 1);
        } catch (Throwable) {
            $this->error('Dates must be Y-m-d.');

            return null;
        }

        if ($to->gt($today)) {
            $to = $today;
        }
        if ($from->lt($earliest)) {
            $this->warn("Google keeps 16 months: starting at {$earliest->toDateString()}.");
            $from = $earliest;
        }
        if ($from->gt($to)) {
            $this->error('--from is after --to.');

            return null;
        }

        $days = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    /**
     * Every dimension's rows for one day, as [dim, key, clicks, impressions,
     * position_sum]; null when Google has nothing for the day yet (so
     * stored rows are kept, not wiped).
     */
    private function fetchDay(SearchConsole $console, CarbonImmutable $day): ?array
    {
        $rows = [];

        foreach (self::DIMENSIONS as $dim => $dimensions) {
            $fetched = $this->fetch($console, $day, $dimensions);
            if ($dim === 'all' && $fetched === []) {
                return null;
            }

            foreach ($fetched as $row) {
                $key = $this->key($dim, $row['keys'] ?? []);
                if ($key === null) {
                    continue;
                }
                $impressions = (int) ($row['impressions'] ?? 0);
                $rows[] = [$dim, $key, (int) ($row['clicks'] ?? 0), $impressions, (float) ($row['position'] ?? 0) * $impressions];
            }
        }

        return $rows;
    }

    private function fetch(SearchConsole $console, CarbonImmutable $day, array $dimensions): array
    {
        $rows = [];

        for ($start = 0; $start < self::MAX_ROWS; $start += self::PAGE_ROWS) {
            Sleep::for(self::PAUSE_MS)->milliseconds();

            $page = $console->query(array_filter([
                'startDate' => $day->toDateString(),
                'endDate' => $day->toDateString(),
                'dimensions' => $dimensions,
                'type' => 'web',
                'dataState' => 'final',
                'rowLimit' => min(self::PAGE_ROWS, self::MAX_ROWS - $start),
                'startRow' => $start,
            ], fn ($value) => $value !== []));

            array_push($rows, ...$page);

            if (count($page) < self::PAGE_ROWS) {
                break;
            }
        }

        return $rows;
    }

    /** A row's stored key, or null to skip it. */
    private function key(string $dim, array $keys): ?string
    {
        $clean = fn ($text) => trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $text) ?? '');

        $key = match ($dim) {
            'all' => '',
            'query' => mb_substr($clean($keys[0] ?? ''), 0, 191),
            'page' => SearchConsole::pageKey((string) ($keys[0] ?? '')),
            'country' => $this->country((string) ($keys[0] ?? '')),
            'device' => strtolower((string) ($keys[0] ?? '')),
            'query_page' => ($keys[0] ?? '') === '' || ($keys[1] ?? '') === '' ? '' : mb_substr($clean($keys[0]), 0, self::PAIR_SIDE).' > '.mb_substr(SearchConsole::pageKey((string) $keys[1]), 0, self::PAIR_SIDE),
        };

        return $dim !== 'all' && $key === '' ? null : $key;
    }

    /** Google's countries are ISO alpha-3 ("usa"); stored as alpha-2 ("US") like the site's own analytics. */
    private function country(string $code): string
    {
        $this->countries ??= json_decode((string) file_get_contents(resource_path('data/country-alpha3.json')), true) ?: [];
        $code = strtoupper($code);

        return $this->countries[$code] ?? $code;
    }

    /**
     * Replaces one day's rows. Keys that land on the same row (the column
     * is case and accent insensitive, and long ones are cut) add up.
     */
    private function store(CarbonImmutable $day, array $rows): void
    {
        DB::transaction(function () use ($day, $rows) {
            DB::table('search_console_daily')->where('day', $day->toDateString())->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                $bindings = [];
                foreach ($chunk as [$dim, $key, $clicks, $impressions, $positionSum]) {
                    array_push($bindings, $day->toDateString(), $dim, $key, $clicks, $impressions, $positionSum);
                }

                DB::insert('INSERT INTO search_console_daily (day, dim, `key`, clicks, impressions, position_sum) VALUES '
                    .implode(',', array_fill(0, count($chunk), '(?, ?, ?, ?, ?, ?)'))
                    .' ON DUPLICATE KEY UPDATE clicks = clicks + VALUES(clicks), impressions = impressions + VALUES(impressions), position_sum = position_sum + VALUES(position_sum)', $bindings);
            }
        });
    }

    /**
     * Replaces one month of search_console_monthly with the sums of its
     * daily rows, every dim at once, in one transaction. Grouped in the key
     * column's own collation, so lines that are one key in the daily table
     * are one here too, under the same spelling the readers show
     * (SearchConsoleReport::SPELLING).
     */
    public function rebuildMonth(CarbonImmutable $month): void
    {
        $first = $month->startOfMonth()->toDateString();
        $last = $month->endOfMonth()->toDateString();

        DB::transaction(function () use ($first, $last) {
            DB::table('search_console_monthly')->where('month', $first)->delete();
            DB::insert('INSERT INTO search_console_monthly (month, dim, `key`, clicks, impressions, position_sum)
                SELECT ?, dim, '.SearchConsoleReport::SPELLING.', SUM(clicks), SUM(impressions), SUM(position_sum)
                FROM search_console_daily WHERE day BETWEEN ? AND ?
                GROUP BY dim, `key`', [$first, $first, $last]);
        });
    }

    /** --rebuild-months: every month the daily table holds (and drops months it no longer does). */
    private function rebuildAllMonths(): int
    {
        $months = DB::table('search_console_daily')
            ->selectRaw("DISTINCT DATE_FORMAT(day, '%Y-%m-01') AS month")
            ->orderBy('month')
            ->pluck('month');

        DB::table('search_console_monthly')->whereNotIn('month', $months->all())->delete();

        foreach ($months as $month) {
            $this->rebuildMonth(CarbonImmutable::parse($month));
            $this->info("{$month}: rebuilt.");
        }

        return self::SUCCESS;
    }
}
