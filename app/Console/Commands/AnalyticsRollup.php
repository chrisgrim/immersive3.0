<?php

namespace App\Console\Commands;

use App\Support\Analytics\Analytics;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds up one UTC day of analytics_events into analytics_daily: per kind
 * of row (type) and per dimension (dim: page, path, event, device, country,
 * utm_source...), how many hits and how many visitors (visitor-days: the
 * salt changes daily, so they add across days), people and bots apart,
 * and for people how many of those visitor-days were browser confirmed
 * and engaged (Analytics::visitorFlagsQuery).
 * Raw rows are pruned (bots after 30 days, people after 13 months); these
 * totals are kept for good, and are what long-range questions read.
 *
 * Idempotent: a day is deleted and rebuilt in one transaction, so a re-run
 * (or a late flush) just replaces it. Only for days whose raw rows are all
 * still there (newer than bot_raw_days, less a margin); older days are
 * skipped. Each statement reads one day through the occurred_at index; a
 * day is a few thousand rows.
 */
class AnalyticsRollup extends Command
{
    protected $signature = 'ei:analytics-rollup {--day= : One UTC day (Y-m-d)} {--from= : First day of a range} {--to= : Last day of a range (default today)}';

    protected $description = 'Build analytics_daily for a day or a range (default: today, yesterday and the day before).';

    /**
     * The type a raw row is totalled under. Every view of a page, whether
     * an event_view (before page views were recorded) or a page_view, is one
     * 'view', so a person on the day page views were switched on is one
     * visitor, not two. A map pan is a 'map_search', apart from searches a
     * person typed (the admin report draws the same line).
     */
    public const VIEW = 'view';

    public const MAP_SEARCH = 'map_search';

    /** Paths through the site: an edge is kept only if this many visitors took it. */
    public const MIN_EDGE_VISITORS = 5;

    /**
     * Each side of an edge key is cut to this many characters, so both fit
     * the 191-character key (94 + ' > ' + 94) and a long first path cannot
     * crowd out the second. AnalyticsQuery::paths() cuts the same way.
     */
    public const EDGE_SIDE = 94;

    /** Two page views are one step only if this close together. */
    public const STEP_MINUTES = 30;

    private const EDGE_KEY = 'CONCAT(LEFT(prev_path, '.self::EDGE_SIDE."), ' > ', LEFT(path, ".self::EDGE_SIDE.'))';

    public function handle(): int
    {
        // One rollup at a time (the nightly and hourly runs have separate
        // scheduler mutexes): two rebuilding the same day at once could each
        // delete, then both insert and merge, doubling it. A run that finds
        // another going waits for it, up to 25 minutes.
        try {
            return Cache::lock('analytics:rollup', 3600)->block(1500, fn () => $this->rollup());
        } catch (LockTimeoutException) {
            $this->warn('Another rollup is still running; skipped.');

            return self::SUCCESS;
        }
    }

    private function rollup(): int
    {
        $failed = false;

        // One bad day must not stop the others (or, a month on, lose bot
        // counts whose raw rows are pruned): report it and carry on.
        // A day is rebuilt from its raw rows, and bot rows are pruned after
        // bot_raw_days: rebuilding an older day would wipe totals that can no
        // longer be recounted, so those days are left as they are. A day with
        // no totals at all has nothing to lose, so it is built from whatever
        // raw rows remain (people's are kept 13 months).
        $oldest = CarbonImmutable::now('UTC')->startOfDay()->subDays(max(7, (int) config('analytics.bot_raw_days', 30)) - 2);

        foreach ($this->days($oldest) as $day) {
            if ($day->lt($oldest) && DB::table('analytics_daily')->where('day', $day->toDateString())->exists()) {
                $this->warn("Skipped {$day->toDateString()}: its raw rows may already be pruned, so its totals are kept as they are.");

                continue;
            }

            try {
                $this->rollupDay($day);
                $this->line("Rolled up {$day->toDateString()}.");
            } catch (\Throwable $e) {
                report($e);
                $this->error("Could not roll up {$day->toDateString()}: {$e->getMessage()}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Days before $before with raw rows but no daily totals: the days after
     * deploy that came before the first run, and any the scheduler missed.
     * Two primary-key/index probes per day, from the first raw row on.
     *
     * @return CarbonImmutable[]
     */
    private function untotalledDays(CarbonImmutable $before): array
    {
        $first = DB::table('analytics_events')->min('occurred_at');
        if ($first === null) {
            return [];
        }

        $days = [];
        for ($day = CarbonImmutable::parse($first, 'UTC')->startOfDay(); $day->lt($before); $day = $day->addDay()) {
            if (! DB::table('analytics_daily')->where('day', $day->toDateString())->exists()
                && DB::table('analytics_events')->whereBetween('occurred_at', [$day->format('Y-m-d H:i:s'), $day->addDay()->subSecond()->format('Y-m-d H:i:s')])->exists()) {
                $days[] = $day;
            }
        }

        return $days;
    }

    /** @return CarbonImmutable[] */
    private function days(CarbonImmutable $oldest): array
    {
        if ($this->option('day')) {
            $day = CarbonImmutable::parse($this->option('day'), 'UTC')->startOfDay();

            // The hourly run (today) also fills untotalled days, so the days
            // before deploy are totalled within the hour, not next night.
            return $day->isToday() ? [...$this->untotalledDays($day), $day] : [$day];
        }

        $to = CarbonImmutable::parse($this->option('to') ?? 'today', 'UTC')->startOfDay();
        $from = $this->option('from') ? CarbonImmutable::parse($this->option('from'), 'UTC')->startOfDay() : $to->subDays(2);

        // The nightly run (no options) also fills every older day that has
        // raw rows but was never totalled.
        $days = $this->option('from') || $this->option('to') ? [] : $this->untotalledDays($from);
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    public function rollupDay(CarbonImmutable $day): void
    {
        $range = [$day->format('Y-m-d H:i:s'), $day->addDay()->format('Y-m-d H:i:s')];

        // At MySQL's default REPEATABLE READ, INSERT ... SELECT share-locks
        // every row it reads until the transaction ends: the flusher's inserts
        // and any save of an event viewed that day would wait on the rollup.
        // READ COMMITTED reads without locking. Applies to the next
        // transaction only, and only when none is open (tests wrap one).
        // Needs row-based binary logging: MySQL refuses INSERT ... SELECT at
        // READ COMMITTED under statement logging, so then it keeps the default.
        $readCommitted = DB::transactionLevel() === 0 && DB::getDriverName() === 'mysql'
            && in_array(DB::scalar('SELECT IF(@@log_bin, @@binlog_format, \'OFF\')'), ['ROW', 'OFF'], true);
        $nextReadsCommitted = fn () => $readCommitted && DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');

        // Browser-confirmed and engaged visitors only on a day the load
        // ping was on (its page views were asked for one); before that,
        // NULL, not 0. Neither column exists before the migration.
        $this->columns = Analytics::hasConfirmationColumns();
        // A whole day only: a day the ping was switched on or off counts
        // as not measured, so a part-measured day never sets confirmed
        // against a whole day's visits. It has people page views and
        // none of them went unasked (two probes on the type and
        // occurred_at index).
        $views = fn () => DB::table('analytics_events')->where('type', Analytics::PAGE_VIEW)->where('bot', 0)
            ->where('occurred_at', '>=', $range[0])->where('occurred_at', '<', $range[1]);
        $this->pinged = $this->columns && $views()->exists() && ! $views()->whereNull('js')->exists();

        try {
            if ($this->pinged) {
                // The day's visitor flags, built once and indexed on visitor
                // for every statement below to join (as a derived table each
                // statement rebuilt it, and joined it without an index).
                // Before the transaction, so no binlog format or GTID rule
                // about temporary tables inside one applies; read committed
                // like the rest.
                [$sql, $bindings] = Analytics::visitorFlagsQuery(...$range);
                DB::statement('DROP TEMPORARY TABLE IF EXISTS '.self::FLAGS);
                $nextReadsCommitted();
                DB::statement('CREATE TEMPORARY TABLE '.self::FLAGS.' (KEY (visitor)) '.$sql, $bindings);
            }

            $nextReadsCommitted();
            DB::transaction(function () use ($day, $range) {
                DB::table('analytics_daily')->where('day', $day->toDateString())->delete();

                foreach ($this->dimensions() as [$types, $dim, $key, $where]) {
                    $this->insert($day, $range, $types, $dim, $key, $where);
                }

                $this->insertEdges($day, $range);
            });
        } finally {
            if ($this->pinged) {
                DB::statement('DROP TEMPORARY TABLE IF EXISTS '.self::FLAGS);
            }
        }
    }

    /** The day's visitor flags (rollupDay), a temporary table on this connection. */
    private const FLAGS = 'analytics_rollup_flags';

    /**
     * [types, dim, key SQL, extra WHERE] for each total kept. Keys are text
     * (ids as digits); NULL keys are skipped by the insert.
     */
    private function dimensions(): array
    {
        $views = [Analytics::PAGE_VIEW, Analytics::EVENT_VIEW];
        $remote = "CAST(JSON_EXTRACT(e.props, '$.remoteLocation') AS UNSIGNED)";

        return [
            [null, 'all', "''", null],
            [$views, 'page', 'COALESCE(e.page, IF(e.type = \''.Analytics::EVENT_VIEW."', 'events.show', NULL))", null],
            // Open-ended text dimensions are kept for people only: bots can
            // send endless distinct values, which would never be pruned.
            [[Analytics::PAGE_VIEW], 'path', 'e.path', 'e.bot = 0'],
            // Not search clicks: those are only believable checked against
            // what their search showed (SiteAnalyticsReport, raw rows).
            [[...$views, Analytics::TICKET_CLICK], 'event', 'e.event_id', null],
            // An event view from before page views carried no organizer id:
            // take it from the event.
            [$views, 'organizer', 'COALESCE(e.organizer_id, ev.organizer_id)', null],
            [$views, 'source', 'e.source', null],
            [$views, 'ref', "JSON_UNQUOTE(JSON_EXTRACT(e.props, '$.ref'))", 'e.bot = 0'],
            [null, 'country', 'e.country', null],
            [null, 'device', 'e.device', null],
            [null, 'browser', 'e.browser', null],
            [null, 'os', 'e.os', null],
            [null, 'city', "IF(e.city IS NULL, NULL, CONCAT(e.city, IF(e.region IS NULL, '', CONCAT(', ', e.region))))", 'e.bot = 0'],
            [[Analytics::PAGE_VIEW], 'utm_source', 'e.utm_source', 'e.bot = 0'],
            [[Analytics::PAGE_VIEW], 'utm_medium', 'e.utm_medium', 'e.bot = 0'],
            [[Analytics::PAGE_VIEW], 'utm_campaign', 'e.utm_campaign', 'e.bot = 0'],
            [[Analytics::SEARCH], 'query', "NULLIF(e.query, '')", "e.source = 'list' AND e.bot = 0"],
            [[Analytics::SEARCH], 'at_home', "IF(COALESCE({$remote}, 0) = 0, 'any', CAST({$remote} AS CHAR))", "e.source = 'list' AND (e.query IS NULL OR e.query = '') AND JSON_UNQUOTE(JSON_EXTRACT(e.props, '$.searchType')) = 'atHome'"],
            [[Analytics::NAV_SEARCH], 'query', "NULLIF(LOWER(e.query), '')", 'e.bot = 0'],
        ];
    }

    /**
     * A key as text in the key column's own collation, cut to its width.
     * JSON values come back binary, so 'café.com' and 'cafe.com' would group
     * apart and then collide in the case- and accent-insensitive primary key.
     */
    private function keyed(string $expression): string
    {
        $collation = preg_replace('/[^a-z0-9_]/', '', (string) config('database.connections.mysql.collation', 'utf8mb4_0900_ai_ci'));

        return "LEFT(CONVERT(({$expression}) USING utf8mb4) COLLATE {$collation}, 191)";
    }

    /** Totals of rows that land on the same key add up instead of failing. */
    private function merge(): string
    {
        return ' ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits), visitors = visitors + VALUES(visitors),
            seconds_sum = seconds_sum + VALUES(seconds_sum), seconds_count = seconds_count + VALUES(seconds_count)'
            .($this->columns ? ', js_visitors = js_visitors + VALUES(js_visitors), engaged_visitors = engaged_visitors + VALUES(engaged_visitors)' : '');
    }

    /** Whether analytics_daily has js_visitors and engaged_visitors yet (rollupDay). */
    private bool $columns = false;

    /** Whether the day being rolled up had the load ping on (rollupDay). */
    private bool $pinged = false;

    /** The column list of an insert into analytics_daily. */
    private function dailyColumns(): string
    {
        return 'day, type, dim, `key`, bot, hits, visitors, seconds_sum, seconds_count'.($this->columns ? ', js_visitors, engaged_visitors' : '');
    }

    /**
     * Joins each visitor of the day to their flags (js, engaged; see
     * Analytics::visitorFlagsQuery) as f on $column, from the temporary
     * table rollupDay built once for the day. Nothing on a day without the
     * ping.
     */
    private function visitorFlags(string $column): string
    {
        return $this->pinged ? 'LEFT JOIN '.self::FLAGS." f ON f.visitor = {$column}" : '';
    }

    /**
     * Visitor-days among a total's visitors that were browser confirmed and
     * engaged: for people's totals only (NULL for bots), and only on a day
     * the ping was on. $bot says whether the group is bots, as an aggregate
     * (ONLY_FULL_GROUP_BY), $visitor the visitor column. Nothing before the
     * migration has run.
     */
    private function flagCounts(string $bot, string $visitor): string
    {
        if (! $this->columns) {
            return '';
        }
        if (! $this->pinged) {
            return ', NULL, NULL';
        }

        return ", IF({$bot}, NULL, COUNT(DISTINCT IF(f.js, {$visitor}, NULL))), IF({$bot}, NULL, COUNT(DISTINCT IF(f.engaged, {$visitor}, NULL)))";
    }

    /**
     * The nav search sends every pause in typing: "sl", "slee", "sleep no".
     * Count only the last of such a run: a query is dropped when the same
     * visitor typed a longer one starting with it within the next minute.
     */
    private function navTypingDone(): string
    {
        return "(e.type <> '".Analytics::NAV_SEARCH."' OR NOT EXISTS (
            SELECT 1 FROM analytics_events n
            WHERE n.type = '".Analytics::NAV_SEARCH."' AND n.visitor = e.visitor AND n.id <> e.id
              AND n.occurred_at >= e.occurred_at AND n.occurred_at <= e.occurred_at + INTERVAL 60 SECOND
              AND CHAR_LENGTH(n.query) > CHAR_LENGTH(e.query)
              AND n.query LIKE CONCAT(REPLACE(REPLACE(REPLACE(e.query, '\\\\', '\\\\\\\\'), '%', '\\\\%'), '_', '\\\\_'), '%')))";
    }

    private function typeOf(): string
    {
        return "CASE WHEN e.type IN ('".Analytics::PAGE_VIEW."', '".Analytics::EVENT_VIEW."') THEN '".self::VIEW."'
            WHEN e.type = '".Analytics::SEARCH."' AND e.source = 'map' THEN '".self::MAP_SEARCH."'
            ELSE e.type END";
    }

    private function insert(CarbonImmutable $day, array $range, ?array $types, string $dim, string $key, ?string $where): void
    {
        $key = $this->keyed($key);
        $flagsSql = $this->visitorFlags('e.visitor');
        $typeSql = $types === null ? '' : ' AND e.type IN ('.implode(',', array_fill(0, count($types), '?')).')';
        $whereSql = $where ? " AND ({$where})" : '';

        // Time on page: each page view's longest page_leave report. The
        // leave may land up to a day later (a view just before midnight UTC,
        // a tab left open); a view_id is one view, so this cannot double count.
        DB::statement("
            INSERT INTO analytics_daily ({$this->dailyColumns()})
            SELECT ?, {$this->typeOf()}, ?, {$key}, e.bot > 0, COUNT(*), COUNT(DISTINCT e.visitor),
                COALESCE(SUM(l.seconds), 0), COUNT(l.seconds){$this->flagCounts('MAX(e.bot) > 0', 'e.visitor')}
            FROM analytics_events e
            LEFT JOIN events ev ON ev.id = e.event_id
            LEFT JOIN (
                SELECT view_id, MAX(seconds) AS seconds FROM analytics_events
                WHERE type = ? AND occurred_at >= ? AND occurred_at < ? + INTERVAL 1 DAY AND view_id IS NOT NULL
                GROUP BY view_id
            ) l ON e.type = ? AND l.view_id = e.view_id
            {$flagsSql}
            WHERE e.occurred_at >= ? AND e.occurred_at < ? AND e.type <> ?{$typeSql}{$whereSql}
              AND {$this->navTypingDone()}
              AND ({$key}) IS NOT NULL
            GROUP BY {$this->typeOf()}, {$key}, e.bot > 0".$this->merge(), [
            $day->toDateString(), $dim,
            Analytics::PAGE_LEAVE, ...$range,
            Analytics::PAGE_VIEW,
            ...$range, Analytics::PAGE_LEAVE, ...($types ?? []),
        ]);
    }

    /**
     * Paths through the site: each step from one page to the next by the
     * same visitor that day, kept only when MIN_EDGE_VISITORS took it (no
     * visitor's own trail is kept past the raw rows' pruning).
     */
    private function insertEdges(CarbonImmutable $day, array $range): void
    {
        $flagsSql = $this->visitorFlags('steps.visitor');

        DB::statement("
            INSERT INTO analytics_daily ({$this->dailyColumns()})
            SELECT ?, '".self::VIEW."', 'edge', {$this->keyed(self::EDGE_KEY)}, 0, COUNT(*), COUNT(DISTINCT steps.visitor), 0, 0
                {$this->flagCounts('FALSE', 'steps.visitor')}
            FROM (
                SELECT visitor, path, LAG(path) OVER w AS prev_path, LAG(occurred_at) OVER w AS prev_at, occurred_at
                FROM analytics_events
                WHERE type = '".Analytics::PAGE_VIEW."' AND bot = 0 AND occurred_at >= ? AND occurred_at < ? AND path IS NOT NULL
                WINDOW w AS (PARTITION BY visitor ORDER BY occurred_at, id)
            ) steps
            {$flagsSql}
            -- A step is two pages within half an hour: a person back hours
            -- later did not go from one to the other.
            WHERE prev_path IS NOT NULL AND occurred_at <= prev_at + INTERVAL ".self::STEP_MINUTES.' MINUTE'."
            GROUP BY {$this->keyed(self::EDGE_KEY)}
            HAVING COUNT(DISTINCT steps.visitor) >= ?".$this->merge(), [$day->toDateString(), ...$range, self::MIN_EDGE_VISITORS]);
    }
}
