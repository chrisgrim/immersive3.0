<?php

namespace App\Console\Commands;

use App\Support\Analytics\Analytics;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Adds up one UTC day of analytics_events into analytics_daily: per kind
 * of row (type) and per dimension (dim: page, path, event, device, country,
 * utm_source...), how many hits and how many visitors (visitor-days: the
 * salt changes daily, so they add across days), people and bots apart.
 * Raw rows are pruned (bots after 30 days, people after 13 months); these
 * totals are kept for good, and are what long-range questions read.
 *
 * Idempotent: a day is deleted and rebuilt in one transaction, so a re-run
 * (or a late flush) just replaces it. Each statement reads one day through
 * the occurred_at index; a day is a few thousand rows.
 */
class AnalyticsRollup extends Command
{
    protected $signature = 'ei:analytics-rollup {--day= : One UTC day (Y-m-d)} {--from= : First day of a range} {--to= : Last day of a range (default today)}';

    protected $description = 'Build analytics_daily for a day or a range (default: today, yesterday and the day before).';

    /** Paths through the site: an edge is kept only if this many visitors took it. */
    public const MIN_EDGE_VISITORS = 5;

    public function handle(): int
    {
        foreach ($this->days() as $day) {
            $this->rollupDay($day);
            $this->line("Rolled up {$day->toDateString()}.");
        }

        return self::SUCCESS;
    }

    /** @return CarbonImmutable[] */
    private function days(): array
    {
        if ($this->option('day')) {
            return [CarbonImmutable::parse($this->option('day'), 'UTC')->startOfDay()];
        }

        $to = CarbonImmutable::parse($this->option('to') ?? 'today', 'UTC')->startOfDay();
        $from = $this->option('from') ? CarbonImmutable::parse($this->option('from'), 'UTC')->startOfDay() : $to->subDays(2);

        $days = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    public function rollupDay(CarbonImmutable $day): void
    {
        $range = [$day->format('Y-m-d H:i:s'), $day->addDay()->format('Y-m-d H:i:s')];

        DB::transaction(function () use ($day, $range) {
            DB::table('analytics_daily')->where('day', $day->toDateString())->delete();

            foreach ($this->dimensions() as [$types, $dim, $key, $where]) {
                $this->insert($day, $range, $types, $dim, $key, $where);
            }

            $this->insertEdges($day, $range);
        });
    }

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
            [[Analytics::PAGE_VIEW], 'path', 'e.path', null],
            [[...$views, Analytics::TICKET_CLICK, Analytics::SEARCH_CLICK], 'event', 'e.event_id', null],
            [[Analytics::PAGE_VIEW], 'organizer', 'e.organizer_id', null],
            [$views, 'source', 'e.source', null],
            [$views, 'ref', "JSON_UNQUOTE(JSON_EXTRACT(e.props, '$.ref'))", null],
            [null, 'country', 'e.country', null],
            [null, 'device', 'e.device', null],
            [null, 'browser', 'e.browser', null],
            [null, 'os', 'e.os', null],
            [null, 'city', "IF(e.city IS NULL, NULL, CONCAT(e.city, IF(e.region IS NULL, '', CONCAT(', ', e.region))))", null],
            [[Analytics::PAGE_VIEW], 'utm_source', 'e.utm_source', null],
            [[Analytics::PAGE_VIEW], 'utm_medium', 'e.utm_medium', null],
            [[Analytics::PAGE_VIEW], 'utm_campaign', 'e.utm_campaign', null],
            [[Analytics::SEARCH], 'query', 'e.query', "e.source = 'list'"],
            [[Analytics::SEARCH], 'at_home', "IF(COALESCE({$remote}, 0) = 0, 'any', CAST({$remote} AS CHAR))", "e.source = 'list' AND (e.query IS NULL OR e.query = '') AND JSON_UNQUOTE(JSON_EXTRACT(e.props, '$.searchType')) = 'atHome'"],
            [[Analytics::NAV_SEARCH], 'query', 'LOWER(e.query)', null],
        ];
    }

    private function insert(CarbonImmutable $day, array $range, ?array $types, string $dim, string $key, ?string $where): void
    {
        $typeSql = $types === null ? '' : ' AND e.type IN ('.implode(',', array_fill(0, count($types), '?')).')';
        $whereSql = $where ? " AND ({$where})" : '';

        // Time on page: each page view's longest page_leave report that day.
        DB::statement("
            INSERT INTO analytics_daily (day, type, dim, `key`, bot, hits, visitors, seconds_sum, seconds_count)
            SELECT ?, e.type, ?, LEFT({$key}, 191), e.bot > 0, COUNT(*), COUNT(DISTINCT e.visitor),
                COALESCE(SUM(l.seconds), 0), COUNT(l.seconds)
            FROM analytics_events e
            LEFT JOIN (
                SELECT view_id, MAX(seconds) AS seconds FROM analytics_events
                WHERE type = ? AND occurred_at >= ? AND occurred_at < ? AND view_id IS NOT NULL
                GROUP BY view_id
            ) l ON e.type = ? AND l.view_id = e.view_id
            WHERE e.occurred_at >= ? AND e.occurred_at < ? AND e.type <> ?{$typeSql}{$whereSql}
              AND ({$key}) IS NOT NULL
            GROUP BY e.type, LEFT({$key}, 191), e.bot > 0", [
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
        DB::statement("
            INSERT INTO analytics_daily (day, type, dim, `key`, bot, hits, visitors, seconds_sum, seconds_count)
            SELECT ?, '".Analytics::PAGE_VIEW."', 'edge', LEFT(CONCAT(prev_path, ' > ', path), 191), 0, COUNT(*), COUNT(DISTINCT visitor), 0, 0
            FROM (
                SELECT visitor, path, LAG(path) OVER (PARTITION BY visitor ORDER BY occurred_at, id) AS prev_path
                FROM analytics_events
                WHERE type = '".Analytics::PAGE_VIEW."' AND bot = 0 AND occurred_at >= ? AND occurred_at < ? AND path IS NOT NULL
            ) steps
            WHERE prev_path IS NOT NULL
            GROUP BY LEFT(CONCAT(prev_path, ' > ', path), 191)
            HAVING COUNT(DISTINCT visitor) >= ?", [$day->toDateString(), ...$range, self::MIN_EDGE_VISITORS]);
    }
}
