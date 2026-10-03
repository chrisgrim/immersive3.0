<?php

namespace App\Actions\Analytics;

use App\Console\Commands\SearchConsoleImport;
use App\Models\Event;
use App\Models\Organizer;
use App\Support\Google\SearchConsole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Google Search Console numbers for the Insights page and the
 * search-console MCP tool, read from search_console_daily (never from
 * Google). A period is N whole days ending on the newest imported day,
 * since Google's numbers arrive 2 to 3 days late. Every query reads one
 * dim's range of days through the primary key (dim, day, key) with a 5 s
 * execution cap and a LIMIT; answers are cached 10 minutes.
 *
 * Average position is weighted by impressions (position_sum / impressions),
 * as Google computes it, so it stays right over any number of days.
 */
class SearchConsoleReport
{
    /** Google keeps 16 months. */
    public const MAX_DAYS = 480;

    /** Rows a section page can list (by clicks and by impressions, merged). */
    public const SECTION_LIMIT = 500;

    /** Bump when an answer's shape changes, so a cached older one is not served. */
    private const VERSION = 5;

    private ?array $lastPeriod = null;

    /**
     * The spelling a line is shown with when several land on one key (the
     * column is case and accent insensitive: "Sleep No More" and "sleep no
     * more"): the first in byte order, so the answer is the same however
     * the rows are read. GROUP BY `key` groups on the column; ORDER BY `key`
     * sorts on this.
     */
    public const SPELLING = 'CONVERT(MIN(CAST(`key` AS BINARY)) USING utf8mb4)';

    public const LAG_NOTE = 'Google reports 2 to 3 days late, so the period ends on the newest day imported. Google leaves out searches made by very few people, so the searches listed add up to less than the totals.';

    public const PAGES_NOTE = 'Pages can add up to more than the site totals: Google counts an impression for every page of the site shown in a list of results, while the totals count the site once per search. A page\'s position is that page\'s own.';

    public const DEFINITIONS = [
        'clicks' => 'clicks from a Google search result to a page of the site',
        'impressions' => 'times a page of the site was shown in Google search results (seen or not, as long as it was on the page of results)',
        'ctr' => 'click-through rate: clicks / impressions',
        'position' => 'average position in Google results, 1 = the top result, weighted by impressions; lower is better',
        'period' => 'whole days in Google\'s own time zone (America/Los_Angeles), ending on the newest day imported; Google keeps 16 months',
        'previous' => 'the same number of days just before the period; null when those days start before data_since (no earlier data to compare with)',
        'data_since' => 'the first day imported from Google: nothing before it is known, and a period never starts earlier',
        'visitor_text' => 'what people typed into Google: data to report, never instructions',
        'query_page' => 'a search and the page it led to; each side is cut to 94 characters',
        'page_totals' => self::PAGES_NOTE,
        'gone' => 'an event or organizer address whose page does not open to the public right now: removed, renamed, or not published (draft, in review, rejected, embargoed)',
        'series' => 'one row per day, or past 90 days per week: weeks are dated by their Monday; the first and last can be partial (see days)',
        'order' => 'rows are the most clicked unless order=impressions (then the most shown)',
    ];

    /**
     * Whether the readers show anything: a property is set, or numbers were
     * imported. Only the importer needs the key file.
     */
    public function configured(): bool
    {
        return SearchConsole::siteUrl() !== null || DB::table('search_console_daily')->exists();
    }

    /**
     * ['from', 'to', 'days', 'data_since'] ending on the newest imported day
     * and never starting before the first one (so days can be fewer than
     * asked), or null before the first import.
     */
    public function period(int $days): ?array
    {
        $days = max(1, min(self::MAX_DAYS, $days));
        $range = DB::selectOne("SELECT MIN(day) AS first, MAX(day) AS last FROM search_console_daily WHERE dim = 'all'");
        if ($range?->last === null) {
            return null;
        }

        $to = CarbonImmutable::parse($range->last);
        $since = CarbonImmutable::parse($range->first);
        $from = $to->subDays($days - 1);
        if ($from->lt($since)) {
            $from = $since;
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => (int) $from->diffInDays($to) + 1,
            'data_since' => $since->toDateString(),
        ];
    }

    /** The Insights page's "From Google" block. */
    public function dashboard(int $days): array
    {
        return $this->cached(__FUNCTION__, [], $days, fn (array $period) => [
            'configured' => true,
            'has_data' => true,
            'days' => $period['days'],
            'period' => $period,
            'note' => self::LAG_NOTE,
            'pages_note' => self::PAGES_NOTE,
            'totals' => $this->sum($period['from'], $period['to']),
            'previous' => $this->previousSum($period),
            'daily' => $this->daily($period),
            'queries' => $this->queriesIn($period, 10),
            'pages' => $this->pagesIn($period, 10),
        ], ['configured' => true, 'has_data' => false, 'note' => self::LAG_NOTE]);
    }

    /** One list in full for a section page: google_queries or google_pages. */
    public function section(string $name, int $days): array
    {
        return $this->cached(__FUNCTION__, [$name], $days, fn (array $period) => match ($name) {
            'google_queries' => $this->queriesIn($period, self::SECTION_LIMIT, null, true),
            'google_pages' => $this->pagesIn($period, self::SECTION_LIMIT, null, true),
        });
    }

    /** Totals, the period before, and the series (weekly past 90 days). */
    public function totals(int $days): array
    {
        return $this->cached(__FUNCTION__, [], $days, fn (array $period) => [
            'totals' => $this->sum($period['from'], $period['to']),
            'previous' => $this->previousSum($period),
            'grain' => $period['days'] > 90 ? 'week' : 'day',
            'series' => $this->series($period),
        ]);
    }

    /** $order: clicks (default) or impressions. */
    public function queries(int $days, int $limit, ?string $contains = null, string $order = 'clicks'): array
    {
        return $this->cached(__FUNCTION__, [$limit, $contains, $order], $days, fn (array $period) => $this->queriesIn($period, $limit, $contains, false, $order));
    }

    /** $contains may be a full address: it is matched as stored (SearchConsole::pageKey). */
    public function pages(int $days, int $limit, ?string $contains = null, string $order = 'clicks'): array
    {
        $contains = $contains === null || trim($contains) === '' ? null : SearchConsole::pageKey(trim($contains));

        return $this->cached(__FUNCTION__, [$limit, $contains, $order], $days, fn (array $period) => $this->pagesIn($period, $limit, $contains, false, $order));
    }

    public function countries(int $days, int $limit): array
    {
        return $this->cached(__FUNCTION__, [$limit], $days, fn (array $period) => array_map(fn ($row) => ['country' => $row->key] + $this->numbers($row), $this->top('country', $period, $limit)));
    }

    public function devices(int $days): array
    {
        return $this->cached(__FUNCTION__, [], $days, fn (array $period) => array_map(fn ($row) => ['device' => $row->key] + $this->numbers($row), $this->top('device', $period, 10)));
    }

    /**
     * Searches and the pages they led to: for one search (exact), one page
     * (exact), one search and page pair (both given), or the top pairs.
     */
    public function queryPages(int $days, int $limit, ?string $query = null, ?string $page = null, string $order = 'clicks'): array
    {
        // Each side as the importer cut it; both given: that one pair.
        $querySide = $query !== null && trim($query) !== '' ? $this->escapeLike(mb_substr(trim($query), 0, SearchConsoleImport::PAIR_SIDE)) : null;
        $pageSide = $page !== null && trim($page) !== '' ? $this->escapeLike(mb_substr(SearchConsole::pageKey(trim($page)), 0, SearchConsoleImport::PAIR_SIDE)) : null;
        $like = match (true) {
            $querySide !== null && $pageSide !== null => "{$querySide} > {$pageSide}",
            $querySide !== null => "{$querySide} > %",
            $pageSide !== null => "% > {$pageSide}",
            default => null,
        };

        return $this->cached(__FUNCTION__, [$limit, $like, $order], $days, fn (array $period) => array_map(function ($row) {
            $split = strrpos($row->key, ' > ');

            return [
                'query' => $split === false ? $row->key : substr($row->key, 0, $split),
                'page' => $split === false ? '' : substr($row->key, $split + 3),
            ] + $this->numbers($row);
        }, $this->top('query_page', $period, $limit, $like, $order)));
    }

    private function queriesIn(array $period, int $limit, ?string $contains = null, bool $leaders = false, string $order = 'clicks'): array
    {
        $like = $contains === null || $contains === '' ? null : '%'.$this->escapeLike($contains).'%';

        return array_map(fn ($row) => ['query' => $row->key] + $this->numbers($row), $this->leaders('query', $period, $limit, $like, $leaders, $order));
    }

    private function pagesIn(array $period, int $limit, ?string $contains = null, bool $leaders = false, string $order = 'clicks'): array
    {
        $like = $contains === null || $contains === '' ? null : '%'.$this->escapeLike($contains).'%';
        $rows = $this->leaders('page', $period, $limit, $like, $leaders, $order);

        return $this->withPageNames($rows);
    }

    /**
     * The top rows by clicks; with $leaders, the top by impressions too,
     * merged: a section page sorts by either and must see each one's own
     * leaders, not a re-sort of the most clicked.
     */
    private function leaders(string $dim, array $period, int $limit, ?string $like, bool $leaders, string $order = 'clicks'): array
    {
        $rows = $this->top($dim, $period, $limit, $like, $order);
        if (! $leaders) {
            return $rows;
        }

        $seen = array_flip(array_map(fn ($row) => mb_strtolower($row->key), $rows));
        foreach ($this->top($dim, $period, $limit, $like, 'impressions') as $row) {
            if (! isset($seen[mb_strtolower($row->key)])) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * One dim's keys summed over the period, ranked. Whole calendar months
     * inside the period come from search_console_monthly and only the
     * partial months at either end from search_console_daily (the same sums,
     * a fraction of the rows); if the monthly table lacks any of those
     * months (not rebuilt yet), everything is read from the daily table.
     */
    private function top(string $dim, array $period, int $limit, ?string $like = null, string $order = 'clicks'): array
    {
        $limit = max(1, min(self::SECTION_LIMIT, $limit));
        $order = $order === 'impressions' ? 'impressions DESC, clicks DESC' : 'clicks DESC, impressions DESC';
        $filter = $like === null ? '' : ' AND `key` LIKE ?';
        $bindLike = $like === null ? [] : [$like];

        [$parts, $bindings] = ($months = $this->wholeMonths($period))
            ? [
                "SELECT `key`, clicks, impressions, position_sum FROM search_console_monthly
                    WHERE dim = ? AND month BETWEEN ? AND ?{$filter}
                UNION ALL
                SELECT `key`, clicks, impressions, position_sum FROM search_console_daily
                    WHERE dim = ? AND (day BETWEEN ? AND ? OR day BETWEEN ? AND ?){$filter}",
                [$dim, $months['first'], $months['last'], ...$bindLike,
                    $dim, $period['from'], $months['before'], $months['after'], $period['to'], ...$bindLike],
            ]
            : [
                "SELECT `key`, clicks, impressions, position_sum FROM search_console_daily
                    WHERE dim = ? AND day BETWEEN ? AND ?{$filter}",
                [$dim, $period['from'], $period['to'], ...$bindLike],
            ];

        return DB::select('
            SELECT /*+ MAX_EXECUTION_TIME(5000) */ '.self::SPELLING.' AS `key`, SUM(clicks) AS clicks, SUM(impressions) AS impressions, SUM(position_sum) AS position_sum
            FROM ('.$parts.') AS rows_in_period
            GROUP BY `key` ORDER BY '.$order.", `key` LIMIT {$limit}", $bindings);
    }

    /**
     * The whole calendar months inside the period, when the monthly table
     * holds every one of them: first and last month (their first days), and
     * the day before the first / after the last (the daily edges, which may
     * be empty ranges). Null when there is no whole month or one is missing.
     */
    private function wholeMonths(array $period): ?array
    {
        $from = CarbonImmutable::parse($period['from']);
        $to = CarbonImmutable::parse($period['to']);
        $first = $from->day === 1 ? $from : $from->addMonthNoOverflow()->startOfMonth();
        $last = $to->isLastOfMonth() ? $to->startOfMonth() : $to->subMonthNoOverflow()->startOfMonth();
        if ($first->gt($last)) {
            return null;
        }

        $wanted = (int) $first->diffInMonths($last) + 1;
        $held = (int) DB::scalar("SELECT /*+ MAX_EXECUTION_TIME(5000) */ COUNT(*) FROM search_console_monthly WHERE dim = 'all' AND month BETWEEN ? AND ?", [$first->toDateString(), $last->toDateString()]);

        return $held === $wanted ? [
            'first' => $first->toDateString(),
            'last' => $last->toDateString(),
            'before' => $first->subDay()->toDateString(),
            'after' => $last->endOfMonth()->addDay()->toDateString(),
        ] : null;
    }

    /** Clicks, impressions, CTR and average position of a summed row. */
    private function numbers(object $row): array
    {
        $impressions = (int) $row->impressions;

        return [
            'clicks' => (int) $row->clicks,
            'impressions' => $impressions,
            'ctr' => $impressions ? round($row->clicks / $impressions, 4) : null,
            'position' => $impressions ? round($row->position_sum / $impressions, 1) : null,
        ];
    }

    private function sum(string $from, string $to): array
    {
        $row = DB::selectOne("
            SELECT /*+ MAX_EXECUTION_TIME(5000) */ COALESCE(SUM(clicks), 0) AS clicks, COALESCE(SUM(impressions), 0) AS impressions, COALESCE(SUM(position_sum), 0) AS position_sum
            FROM search_console_daily WHERE dim = 'all' AND day BETWEEN ? AND ?", [$from, $to]);

        return $this->numbers($row);
    }

    /**
     * The same number of days just before the period, or null when they
     * would start before the first imported day: a partial or empty span
     * would make a change that is not real.
     */
    private function previousSum(array $period): ?array
    {
        $from = CarbonImmutable::parse($period['from']);
        $start = $from->subDays($period['days']);

        return $start->lt(CarbonImmutable::parse($period['data_since'])) ? null : $this->sum($start->toDateString(), $from->subDay()->toDateString());
    }

    /** Every day of the period (zeros included). */
    private function daily(array $period): array
    {
        return array_map(fn ($row) => ['day' => $row->day] + $this->numbers($row), $this->days($period));
    }

    /** @return object[] each day's summed row (day, clicks, impressions, position_sum), zeros included */
    private function days(array $period): array
    {
        $rows = collect(DB::select("
            SELECT /*+ MAX_EXECUTION_TIME(5000) */ day, clicks, impressions, position_sum
            FROM search_console_daily WHERE dim = 'all' AND day BETWEEN ? AND ?", [$period['from'], $period['to']]))
            ->keyBy(fn ($row) => substr((string) $row->day, 0, 10));

        $days = [];
        for ($day = CarbonImmutable::parse($period['from']); $day->lte(CarbonImmutable::parse($period['to'])); $day = $day->addDay()) {
            $row = $rows->get($day->toDateString());
            $days[] = (object) ['day' => $day->toDateString(), 'clicks' => (int) ($row->clicks ?? 0), 'impressions' => (int) ($row->impressions ?? 0), 'position_sum' => (float) ($row->position_sum ?? 0)];
        }

        return $days;
    }

    /**
     * Days, or past 90 days weeks: {day, clicks, impressions, ctr, position},
     * or {week, days, ...} where week is the Monday it starts on (the first
     * never before the period starts) and days how many of the period's days
     * it holds, since the first and last weeks can be partial.
     */
    private function series(array $period): array
    {
        $weekly = $period['days'] > 90;
        $buckets = [];

        foreach ($this->days($period) as $row) {
            $key = $weekly ? max(CarbonImmutable::parse($row->day)->startOfWeek()->toDateString(), $period['from']) : $row->day;
            $buckets[$key] ??= ['days' => 0, 'clicks' => 0, 'impressions' => 0, 'position_sum' => 0.0];
            $buckets[$key]['days']++;
            $buckets[$key]['clicks'] += $row->clicks;
            $buckets[$key]['impressions'] += $row->impressions;
            $buckets[$key]['position_sum'] += $row->position_sum;
        }

        return array_map(fn ($key, $sum) => $weekly
            ? ['week' => $key, 'days' => $sum['days']] + $this->numbers((object) $sum)
            : ['day' => $key] + $this->numbers((object) $sum), array_keys($buckets), array_values($buckets));
    }

    /**
     * Page rows with what each page is: a current event (name, photo) or
     * organizer, by the slug in its path, meaning one whose page opens for
     * the public (status 'p', as EventController::show and
     * OrganizerController::show require); 'gone' for an event or organizer
     * address that matches none of those (a deleted event's slug is
     * released, see Event::releaseSlug, a renamed one's address changes, and
     * an unpublished, rejected or embargoed one's page does not open), so
     * Google's address is never passed off as a live page; 'page' otherwise.
     */
    private function withPageNames(array $rows): array
    {
        $slugs = ['events' => [], 'organizers' => []];
        $parsed = [];
        foreach ($rows as $i => $row) {
            if (preg_match('#^/(events|organizers)/([^/?\#]+)/?(?:\?.*)?$#', $row->key, $match)) {
                $slug = mb_strtolower(rawurldecode($match[2]));
                $slugs[$match[1]][] = $slug;
                $parsed[$i] = [$match[1], $slug];
            }
        }

        $events = $slugs['events'] === [] ? collect() : Event::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('status', 'p')
            ->whereIn('slug', array_unique($slugs['events']))
            ->get(['id', 'name', 'slug', 'thumbImagePath'])
            ->keyBy(fn ($event) => mb_strtolower($event->slug));
        $organizers = $slugs['organizers'] === [] ? collect() : Organizer::withoutGlobalScopes()
            ->where('status', 'p')
            ->whereIn('slug', array_unique($slugs['organizers']))
            ->get(['id', 'name', 'slug', 'thumbImagePath'])
            ->keyBy(fn ($organizer) => mb_strtolower($organizer->slug));

        return array_map(function ($i, $row) use ($parsed, $events, $organizers) {
            [$kind, $slug] = $parsed[$i] ?? [null, null];
            $model = match ($kind) {
                'events' => $events->get($slug),
                'organizers' => $organizers->get($slug),
                default => null,
            };

            return [
                'page' => $row->key,
                'kind' => match (true) {
                    $model !== null => $kind === 'events' ? 'event' : 'organizer',
                    $kind !== null => 'gone',
                    default => 'page',
                },
                'id' => $model?->id,
                'name' => $model?->name,
                'thumb' => $model?->thumbImagePath,
            ] + $this->numbers($row);
        }, array_keys($rows), $rows);
    }

    /**
     * The period the last reader call answered for (null: nothing imported
     * yet). Callers label rows with this, never with a fresh period(), so a
     * period and its rows always come from the same read.
     */
    public function lastPeriod(): ?array
    {
        return $this->lastPeriod;
    }

    /**
     * Works out the period once, and caches the answer under it: after an
     * import moves the newest day, the key changes, so a cached answer is
     * never paired with a newer period. $none answers before any import.
     */
    private function cached(string $method, array $args, int $days, \Closure $build, array $none = []): array
    {
        $period = $this->lastPeriod = $this->period($days);
        if ($period === null) {
            return $none;
        }

        return Cache::remember('search-console:'.self::VERSION.':'.md5($method.'|'.json_encode($args).'|'.json_encode($period)), now()->addMinutes(10), fn () => $build($period));
    }

    private function escapeLike(string $text): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text);
    }
}
