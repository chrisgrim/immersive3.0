<?php

namespace App\Actions\Analytics;

use App\Console\Commands\AnalyticsRollup;
use App\Models\Event;
use App\Models\Events\RemoteLocation;
use App\Support\Analytics\Analytics;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The first-party analytics summary behind the admin Analytics page and the
 * get-site-analytics MCP tool: one shape, so both always agree. Humans only
 * (bot = 0) except the `bots` section, which says how much was filtered.
 *
 * "Searches" are the ones a person typed or picked (source = list). A map
 * pan is also recorded as a search (source = map) but keeps the city it
 * started from, so counting it would turn one Los Angeles search and twenty
 * drags out to sea into 21 Los Angeles searches, most "found nothing".
 *
 * Cached for 10 minutes per range, and built by one request at a time
 * (MAX_DAYS says why): the server has few PHP workers.
 */
class SiteAnalyticsReport
{
    /**
     * Prod logs ~15k notes a day, so a year is ~5M rows and minutes of
     * queries on a 2-CPU server. 90 days keeps a cold build to seconds;
     * a longer view needs a daily rollup table first.
     */
    public const MAX_DAYS = 90;

    private const REMOTE = "CAST(JSON_EXTRACT(analytics_events.props, '$.remoteLocation') AS UNSIGNED)";

    /** An At Home search's line: its online type's name (see withTypeName). */
    private const TYPE_NAME = 'CASE WHEN COALESCE('.self::REMOTE.", 0) = 0 THEN 'Any type'
        ELSE COALESCE(rl.name, CONCAT('Type ', ".self::REMOTE.')) END';

    /** Bump when the report's shape changes (see handle()). */
    private const VERSION = 12;

    private const LIMIT = 25;

    private const FILTER_PATHS = "'$.categories', '$.tags', '$.start', '$.priceMin', '$.priceMax'";

    public function handle(int $days = 30): array
    {
        $days = max(1, min(self::MAX_DAYS, $days));

        // Versioned: a report cached by older code (a different shape) must
        // not be served to newer code right after a deploy.
        $key = 'analytics:report:'.self::VERSION.":{$days}";

        // One build at a time: a second request waits for the first (up to
        // 30s) and then reads its cached result instead of starting another.
        // Throws LockTimeoutException if that is not enough; callers answer
        // "still building, try again" (AdminAnalyticsController, the MCP tool).
        // A cached report is answered at once, without waiting on the lock
        // (section pages hold it too while they build).
        return Cache::get($key) ?? Cache::lock('analytics:report:building', 120)->block(30, fn () => Cache::remember($key, now()->addMinutes(10), fn () => $this->build($days)));
    }

    private function build(int $days): array
    {
        // Whole UTC days: today and the $days - 1 before it.
        $since = now()->subDays($days - 1)->startOfDay();

        return [
            'days' => $days,
            'since' => $since->toIso8601String(),
            // The same span just before, for "vs prior period".
            // Same length, ending at this time of day N days ago, so a
            // part-day today is not set against a whole one.
            ...$this->flagged($since, null, fn () => ['totals' => $this->totals($since), 'countries' => $this->countries($since)]),
            'totals_previous' => $this->flagged($since->copy()->subDays($days), now()->subDays($days), fn () => $this->totals($since->copy()->subDays($days), now()->subDays($days))),
            'zero_result_total' => $this->typedSearches($since)->where('results', 0)->count(),
            'daily' => $this->daily($since, $days),
            'searches' => $this->searches($since),
            'at_home_searches' => $this->atHomeSearches($since),
            'zero_result_searches' => $this->zeroResultSearches($since),
            'events' => $this->events($since),
            'view_sources' => $this->viewSources($since),
            'search_clicks' => $this->searchClicks($since),
            'bots' => $this->bots($since),
        ];
    }

    private function rows($since, ?string $type = null, $until = null): Builder
    {
        return DB::table('analytics_events')
            ->when($type, fn ($query) => $query->where('analytics_events.type', $type))
            ->where('analytics_events.occurred_at', '>=', $since)
            ->when($until, fn ($query) => $query->where('analytics_events.occurred_at', '<', $until))
            ->where('analytics_events.bot', 0);
    }

    /** Searches a person made (not map pans, see the class docblock). */
    private function typedSearches($since): Builder
    {
        return $this->rows($since, Analytics::SEARCH)->where('source', 'list');
    }

    /**
     * Result clicks that are believable: their search was a real typed one,
     * and the event was the one shown at that position. The beacon is a
     * plain POST anyone can send, so a click naming an event or position its
     * search never showed is left out. Past the first page the search row
     * does not list what Show more added, so those positions are taken as
     * given.
     */
    private function believableClicks($since): Builder
    {
        return $this->rows($since, Analytics::SEARCH_CLICK)
            ->join('analytics_events as s', function ($join) use ($since) {
                // The search itself inside the period, so "N of M searches"
                // never counts a click whose search is not among the M.
                $join->on('s.search_id', '=', 'analytics_events.search_id')
                    ->where('s.occurred_at', '>=', $since)
                    ->where('s.type', Analytics::SEARCH)
                    ->where('s.source', 'list')
                    ->where('s.bot', 0);
            })
            // A position no further down than the search's own result count.
            ->whereRaw('CAST(JSON_EXTRACT(analytics_events.props, \'$.position\') AS UNSIGNED) <= COALESCE(s.results, 0)')
            ->whereRaw("(CAST(JSON_EXTRACT(analytics_events.props, '$.position') AS UNSIGNED) > COALESCE(JSON_LENGTH(s.props, '$.shown'), 0)
                OR CAST(JSON_EXTRACT(s.props, CONCAT('$.shown[', CAST(JSON_EXTRACT(analytics_events.props, '$.position') AS UNSIGNED) - 1, ']')) AS UNSIGNED) = analytics_events.event_id)");
    }

    /**
     * A view of an event page: an event_view row (before page views were
     * recorded), or a page_view of events.show (since; RecordPageView).
     */
    private function eventViewSql(): string
    {
        return "(analytics_events.type = '".Analytics::EVENT_VIEW."' OR (analytics_events.type = '".Analytics::PAGE_VIEW."' AND analytics_events.page = 'events.show'))";
    }

    /**
     * Per type (map pans apart, as map_search): how many and by how many
     * different visitors; on the days browser confirmation was measured,
     * also how many visitors those days had, and how many of them were
     * browser confirmed and engaged (see flagged()). 'people' is everyone
     * who did anything, less browsers that said they are automated.
     */
    private function totals($since, $until = null): array
    {
        $flagged = $this->measuredDays !== [];
        $query = $this->rows($since, null, $until)
            ->selectRaw("CASE WHEN analytics_events.type = ? AND analytics_events.source = 'map' THEN 'map_search'
                    WHEN analytics_events.type = ? AND analytics_events.page = 'events.show' THEN ? ELSE analytics_events.type END AS kind,
                COUNT(*) AS total, COUNT(DISTINCT analytics_events.visitor) AS visitors", [Analytics::SEARCH, Analytics::PAGE_VIEW, Analytics::EVENT_VIEW]);
        // People: visitors of rows the server saw (a late beacon is not a
        // visitor-day of its own), less automated browsers.
        $query->selectRaw('COUNT(DISTINCT IF('.$this->serverRow().($flagged ? ' AND NOT COALESCE(f.automated, 0)' : '').', analytics_events.visitor, NULL)) AS people');
        if ($flagged) {
            $this->joinFlags($query)->selectRaw($this->flagCounts(), $this->measuredBindings());
        }

        return $query
            // The rollup line (kind NULL) counts each visitor once overall.
            ->groupByRaw('kind WITH ROLLUP')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->kind ?? 'people' => [
                'total' => (int) $row->total,
                'visitors' => (int) ($row->kind === null ? $row->people : $row->visitors),
            ] + $this->flagFields($row) + ($row->kind === null ? ['measured_since' => $this->measuredDays[0] ?? null] : [])])
            ->all();
    }

    /** Days (Y-m-d) of the range being counted with browser confirmation measured (flagged()). */
    private array $measuredDays = [];

    /**
     * Runs $count with the range's visitor flags (Analytics::visitorFlagsQuery,
     * the daily totals' definitions) built once into a temporary table that
     * every query of it joins as f, so totals and countries share one pass.
     * Only over the days browser confirmation was measured, read from the
     * daily totals (dim, day index): before it, confirmed and engaged are
     * unknown, not zero, and no pass is made at all. Today counts once its
     * hourly rollup has run.
     */
    private function flagged($since, $until, \Closure $count): mixed
    {
        $this->measuredDays = Analytics::hasConfirmationColumns()
            ? DB::table('analytics_daily')->where('dim', 'all')->where('bot', 0)->where('type', AnalyticsRollup::VIEW)
                ->whereBetween('day', [$since->toDateString(), ($until ?? now())->toDateString()])->whereNotNull('js_visitors')
                ->orderBy('day')->pluck('day')->map(fn ($day) => substr((string) $day, 0, 10))->all()
            : [];

        if ($this->measuredDays === []) {
            return $count();
        }

        // A visitor code lasts one day, so per visitor is per visitor-day.
        [$flagsSql, $flagBindings] = Analytics::visitorFlagsQuery(
            max($since->copy(), Carbon::parse($this->measuredDays[0], 'UTC'))->format('Y-m-d H:i:s'),
            $until?->format('Y-m-d H:i:s'),
        );

        try {
            DB::statement('DROP TEMPORARY TABLE IF EXISTS analytics_report_flags');
            // At MySQL's default REPEATABLE READ, CREATE ... SELECT
            // share-locks every row it reads (next-key locks, the supremum
            // too) until it ends: the flusher's inserts and its ping and
            // automation UPDATEs would wait out the whole build. READ
            // COMMITTED reads without locking. Applies to the next
            // transaction only, and only when none is open (tests wrap one).
            // Needs row-based binary logging: MySQL refuses an INSERT ...
            // SELECT at READ COMMITTED under statement logging, so then it
            // keeps the default (as AnalyticsRollup::rollupDay does).
            if (DB::transactionLevel() === 0 && DB::getDriverName() === 'mysql'
                && in_array(DB::scalar('SELECT IF(@@log_bin, @@binlog_format, \'OFF\')'), ['ROW', 'OFF'], true)) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            }
            DB::statement('CREATE TEMPORARY TABLE analytics_report_flags (KEY (visitor)) '.$flagsSql, $flagBindings);

            return $count();
        } finally {
            DB::statement('DROP TEMPORARY TABLE IF EXISTS analytics_report_flags');
            $this->measuredDays = [];
        }
    }

    /** A row the server saw itself (Analytics::SERVER_TYPES), not a beacon. */
    private function serverRow(): string
    {
        return "analytics_events.type IN ('".implode("', '", Analytics::SERVER_TYPES)."')";
    }

    private function joinFlags(Builder $query): Builder
    {
        return $query->leftJoin('analytics_report_flags as f', 'f.visitor', '=', 'analytics_events.visitor');
    }

    /** The row is on a measured day (bindings: the measured days). */
    private function onMeasuredDay(): string
    {
        return 'DATE(analytics_events.occurred_at) IN ('.implode(',', array_fill(0, count($this->measuredDays), '?')).')';
    }

    /** flagCounts()' bindings: the measured days, once for each of its three counts. */
    private function measuredBindings(): array
    {
        return [...$this->measuredDays, ...$this->measuredDays, ...$this->measuredDays];
    }

    /** Visitors on measured days (people only), and of them browser confirmed and engaged. */
    private function flagCounts(): string
    {
        // All three on measured days and server rows only: today counts
        // once its hourly rollup marks it measured, never before.
        $on = "{$this->serverRow()} AND {$this->onMeasuredDay()}";

        return "COUNT(DISTINCT IF({$on} AND NOT COALESCE(f.automated, 0), analytics_events.visitor, NULL)) AS measured,
            COUNT(DISTINCT IF({$on} AND f.js, analytics_events.visitor, NULL)) AS confirmed,
            COUNT(DISTINCT IF({$on} AND f.engaged, analytics_events.visitor, NULL)) AS engaged";
    }

    /** The flag counts of a row, null when nothing in the range was measured. */
    private function flagFields(object $row): array
    {
        $measured = $this->measuredDays !== [];

        return [
            'visitors_on_measured_days' => $measured ? (int) $row->measured : null,
            'confirmed_visitors' => $measured ? (int) $row->confirmed : null,
            'engaged_visitors' => $measured ? (int) $row->engaged : null,
        ];
    }

    /**
     * Per UTC day, every day in the range (zeros included): event views,
     * searches and ticket clicks.
     */
    private function daily($since, int $days): array
    {
        $counts = $this->rows($since)
            ->whereIn('type', [Analytics::EVENT_VIEW, Analytics::PAGE_VIEW, Analytics::SEARCH, Analytics::TICKET_CLICK])
            ->whereRaw("(type <> ? OR source = 'list')", [Analytics::SEARCH])
            ->whereRaw("(type <> ? OR page = 'events.show')", [Analytics::PAGE_VIEW])
            ->selectRaw('DATE(occurred_at) AS day, IF(type = ?, ?, type) AS kind, COUNT(*) AS total', [Analytics::PAGE_VIEW, Analytics::EVENT_VIEW])
            ->groupBy('day', 'kind')
            ->get()
            ->groupBy('day');

        $series = [];
        for ($day = $since->copy()->startOfDay(); $day->lte(now()); $day->addDay()) {
            $rows = $counts->get($day->toDateString(), collect())->pluck('total', 'kind');
            $series[] = [
                'day' => $day->toDateString(),
                'event_views' => (int) ($rows[Analytics::EVENT_VIEW] ?? 0),
                'searches' => (int) ($rows[Analytics::SEARCH] ?? 0),
                'ticket_clicks' => (int) ($rows[Analytics::TICKET_CLICK] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * The places people search most, how often each came back empty, and how
     * many of those searches led to a result click.
     */
    private function searches($since, ?string $contains = null, int $limit = self::LIMIT): array
    {
        $clicked = $this->believableClicks($since)->select('analytics_events.search_id')->distinct();

        return $this->typedSearches($since)
            ->leftJoinSub($clicked, 'clicked', 'clicked.search_id', '=', 'analytics_events.search_id')
            ->whereNotNull('query')
            ->where('query', '!=', '')
            ->when($contains !== null, fn ($query) => $query->where('query', 'like', '%'.self::escapeLike($contains).'%'))
            ->selectRaw('query, COUNT(*) AS searches, SUM(results = 0) AS found_nothing, COUNT(clicked.search_id) AS clicked')
            ->groupBy('query')
            ->orderByDesc('searches')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'place' => $row->query,
                'searches' => (int) $row->searches,
                'found_nothing' => (int) $row->found_nothing,
                'clicked' => (int) $row->clicked,
                'click_rate' => $row->searches > 0 ? round($row->clicked / $row->searches, 3) : null,
            ])
            ->all();
    }

    /** At Home searches by online type (none picked: "any type"). */
    private function atHomeSearches($since, int $limit = self::LIMIT): array
    {
        $clicked = $this->believableClicks($since)->select('analytics_events.search_id')->distinct();

        return $this->withTypeName($this->atHome($this->typedSearches($since)))
            ->leftJoinSub($clicked, 'clicked', 'clicked.search_id', '=', 'analytics_events.search_id')
            ->selectRaw(self::TYPE_NAME.' AS place, COUNT(*) AS searches, SUM(results = 0) AS found_nothing, COUNT(clicked.search_id) AS clicked')
            ->groupBy('place')
            ->orderByDesc('searches')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'place' => ucfirst($row->place),
                'searches' => (int) $row->searches,
                'found_nothing' => (int) $row->found_nothing,
                'clicked' => (int) $row->clicked,
                'click_rate' => $row->searches > 0 ? round($row->clicked / $row->searches, 3) : null,
            ])
            ->all();
    }

    /** At Home searches: no place typed, the At Home tab. */
    private function atHome(Builder $query): Builder
    {
        return $query
            ->where(fn ($none) => $none->whereNull('analytics_events.query')->orWhere('analytics_events.query', ''))
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(analytics_events.props, '$.searchType')) = 'atHome'");
    }

    /**
     * Joins each At Home search to its online type, so lists group on the
     * type's NAME (TYPE_NAME) while counting: remote_locations holds exact
     * duplicate names (two "Sms/Text Message"), and grouping by id would
     * split one type into two lines and count a visitor of both twice.
     */
    private function withTypeName(Builder $query): Builder
    {
        return $query->leftJoin('remote_locations as rl', fn ($join) => $join->on('rl.id', '=', DB::raw(self::REMOTE)));
    }

    /**
     * Admin search boxes: any event whose name contains $text (not only the
     * top 25), or any typed place, over the same range as the report. Not
     * cached; both read an indexed slice (event ids, or typed searches).
     */
    public function findEvents(string $text, int $days = 30): array
    {
        // Every matching event (a few thousand at most), ranked by views
        // inside events(), so a common word cannot crowd out the busiest.
        $ids = Event::withoutGlobalScopes()->withTrashed()
            ->where('name', 'like', '%'.self::escapeLike($text).'%')
            ->pluck('id')
            ->all();

        return $ids === [] ? [] : $this->events($this->since($days), $ids);
    }

    public function findPlaces(string $text, int $days = 30): array
    {
        return $this->searches($this->since($days), $text);
    }

    /** Searches that found nothing, for a typed place or an At Home type whose name matches. */
    public function findUnmet(string $text, int $days = 30): array
    {
        return $this->zeroResultSearches($this->since($days), $text);
    }

    /**
     * How many rows a section page can list (events: this many of each of
     * its three leader lists, merged, see events()).
     */
    public const SECTION_LIMIT = 500;

    /**
     * One section of the report in full (up to SECTION_LIMIT rows), for the
     * admin page's section view, which sorts and filters them itself.
     * Cached like the report.
     */
    public function section(string $name, int $days = 30): array
    {
        $days = max(1, min(self::MAX_DAYS, $days));
        $since = $this->since($days);
        $limit = self::SECTION_LIMIT;

        $key = 'analytics:section:'.self::VERSION.":{$name}:{$days}";
        $build = fn () => Cache::remember($key, now()->addMinutes(10), fn () => match ($name) {
            'places' => $this->searches($since, null, $limit),
            'unmet' => $this->zeroResultSearches($since, null, $limit),
            'at_home' => $this->atHomeSearches($since, $limit),
            'events' => $this->events($since, null, $limit),
            'sources' => $this->viewSources($since, $limit),
            'countries' => $this->flagged($since, null, fn () => $this->countries($since, 250)),
        });

        // A cold build waits behind any other report build (same lock as
        // handle()), so quick taps between ranges cannot stack up scans.
        return Cache::get($key) ?? Cache::lock('analytics:report:building', 120)->block(30, $build);
    }

    private function since(int $days)
    {
        return now()->subDays(max(1, min(self::MAX_DAYS, $days)) - 1)->startOfDay();
    }

    private static function escapeLike(string $text): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text);
    }

    /**
     * Searches that found nothing, by place: the gaps worth filling. Split
     * into "with filters" (the filters may be why) and plain.
     */
    private function zeroResultSearches($since, ?string $contains = null, int $limit = self::LIMIT): array
    {
        $like = $contains === null ? null : '%'.self::escapeLike($contains).'%';
        $columns = "COUNT(*) AS searches,
            SUM(JSON_CONTAINS_PATH(COALESCE(props, '{}'), 'one', ".self::FILTER_PATHS.')) AS with_filters,
            COUNT(DISTINCT visitor) AS visitors, MAX(occurred_at) AS last_searched';

        // Typed places, grouped on the text in its own collation, so
        // "Austin" and "austin" are one place.
        $places = $this->typedSearches($since)
            ->where('results', 0)
            ->whereNotNull('analytics_events.query')
            ->where('analytics_events.query', '!=', '')
            ->when($like !== null, fn ($query) => $query->where('analytics_events.query', 'like', $like))
            ->selectRaw("analytics_events.query AS place, {$columns}")
            ->groupBy('analytics_events.query')
            ->orderByDesc('searches')
            ->limit($limit)
            ->get()
            // each() stops at a callback returning false, so no arrow fn here.
            ->each(function ($row) {
                $row->kind = 'place';
            });

        // At Home searches, grouped by online type: a separate list, so no
        // typed text can be mistaken for one.
        $types = $like === null ? null : RemoteLocation::where('name', 'like', $like)->pluck('id')->all();
        $atHome = $types === [] ? collect() : $this->atHome($this->typedSearches($since))
            ->where('results', 0)
            ->when($types !== null, fn ($query) => $query->whereIn(DB::raw(self::REMOTE), $types))
            ->pipe(fn ($query) => $this->withTypeName($query))
            ->selectRaw(self::TYPE_NAME." AS place, {$columns}")
            ->groupBy('place')
            ->orderByDesc('searches')
            ->limit($limit)
            ->get()
            ->each(function ($row) {
                $row->kind = 'at_home';
                $row->place = ucfirst($row->place);
            });

        // No place and not At Home (all events, with only filters set): one
        // 'no_place' line, so the rows add up to zero_result_total. Not
        // searchable.
        $noPlace = $like !== null ? collect() : $this->typedSearches($since)
            ->where('results', 0)
            ->where(fn ($none) => $none->whereNull('analytics_events.query')->orWhere('analytics_events.query', ''))
            ->whereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(analytics_events.props, '$.searchType')), '') <> 'atHome'")
            ->selectRaw("'' AS place, {$columns}")
            ->havingRaw('COUNT(*) > 0')
            ->get()
            ->each(function ($row) {
                $row->kind = 'no_place';
            });

        return $places->concat($atHome)->concat($noPlace)
            ->sortByDesc('searches')
            ->take($limit)
            ->map(fn ($row) => [
                // 'place' (typed), 'at_home' (place = the online type) or
                // 'no_place' (one line, place empty): what the line is,
                // never read from its text, which visitors control.
                'kind' => $row->kind,
                'place' => $row->place,
                'searches' => (int) $row->searches,
                'with_filters' => (int) $row->with_filters,
                'visitors' => (int) $row->visitors,
                'last_searched' => $row->last_searched,
            ])
            ->values()
            ->all();
    }

    /** The most viewed events, with their ticket clicks and click-through. */
    private function events($since, ?array $onlyIds = null, int $limit = self::LIMIT): array
    {
        // The top by views, by ticket clicks and by click-through (10+
        // views), merged: the page sorts by any of the three, and each must
        // see its own leaders, not a re-sort of the most viewed.
        $base = fn () => $this->rows($since)
            ->whereRaw("({$this->eventViewSql()} OR analytics_events.type = ?)", [Analytics::TICKET_CLICK])
            ->when($onlyIds !== null, fn ($query) => $query->whereIn('event_id', $onlyIds), fn ($query) => $query->whereNotNull('event_id'))
            ->selectRaw("event_id, SUM({$this->eventViewSql()}) AS views, SUM(type = ?) AS ticket_clicks", [Analytics::TICKET_CLICK])
            ->groupBy('event_id')
            ->limit($limit);

        $counts = $base()->orderByDesc('views')->get()
            ->concat($base()->orderByDesc('ticket_clicks')->having('ticket_clicks', '>', 0)->get())
            ->concat($base()->having('views', '>=', 10)->orderByRaw('ticket_clicks / views DESC')->get())
            ->unique('event_id')
            ->sortByDesc('views')
            ->values();

        $events = Event::withoutGlobalScopes()->withTrashed()
            ->with('location:id,event_id,city,region,country')
            ->whereIn('id', $counts->pluck('event_id'))
            ->get(['id', 'name', 'slug', 'thumbImagePath', 'hasLocation', 'deleted_at'])
            ->keyBy('id');

        return $counts->map(fn ($row) => [
            'event_id' => (int) $row->event_id,
            'name' => $events[$row->event_id]->name ?? null,
            'slug' => isset($events[$row->event_id]) && ! $events[$row->event_id]->trashed() ? $events[$row->event_id]->slug : null,
            'thumb' => $events[$row->event_id]->thumbImagePath ?? null,
            'city' => $events[$row->event_id]->location->city ?? null,
            'online' => isset($events[$row->event_id]) && ! $events[$row->event_id]->hasLocation,
            'views' => (int) $row->views,
            'ticket_clicks' => (int) $row->ticket_clicks,
            'click_through' => $row->views > 0 ? round($row->ticket_clicks / $row->views, 3) : null,
        ])->all();
    }

    /** Where event page views came from (Analytics::referrer), and the top outside sites. */
    private function viewSources($since, int $limit = self::LIMIT): array
    {
        $sources = $this->rows($since)->whereRaw($this->eventViewSql())
            ->selectRaw('source, COUNT(*) AS views')
            ->groupBy('source')
            ->orderByDesc('views')
            ->pluck('views', 'source')
            ->map(fn ($views) => (int) $views)
            ->all();

        $sites = $this->rows($since)->whereRaw($this->eventViewSql())
            ->whereNotNull('props')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(props, '$.ref')) AS site, COUNT(*) AS views")
            ->groupBy('site')
            ->havingRaw('site IS NOT NULL')
            ->orderByDesc('views')
            ->limit($limit)
            ->pluck('views', 'site')
            ->map(fn ($views) => (int) $views)
            ->all();

        return ['by_kind' => $sources, 'outside_sites' => $sites];
    }

    /** How often a search leads to a result click, and where in the list the clicks land. */
    private function searchClicks($since): array
    {
        // Typed searches only, on both sides of the rate (class docblock).
        $searches = $this->typedSearches($since)->whereNotNull('search_id')->count();
        $clicked = $this->believableClicks($since)->distinct()->count('analytics_events.search_id');

        $positions = $this->believableClicks($since)
            // Each result counted once per search: the same click sent again
            // (a double click, or a script replaying it) is one click.
            ->selectRaw("LEAST(CAST(JSON_EXTRACT(analytics_events.props, '$.position') AS UNSIGNED), 11) AS position, COUNT(DISTINCT analytics_events.search_id, analytics_events.event_id) AS clicks")
            ->groupBy('position')
            ->orderBy('position')
            ->pluck('clicks', 'position')
            ->mapWithKeys(fn ($clicks, $position) => [($position >= 11 ? '11+' : (string) $position) => (int) $clicks])
            ->all();

        return [
            'searches' => $searches,
            'searches_with_a_click' => $clicked,
            'click_rate' => $searches > 0 ? round($clicked / $searches, 3) : null,
            'by_position' => $positions,
        ];
    }

    /**
     * Visitors by country, and on the days browser confirmation was
     * measured, those days' visitors and how many were browser confirmed
     * (null when nothing was measured). Inside flagged().
     */
    private function countries($since, int $limit = 15): array
    {
        $query = $this->rows($since)
            ->whereNotNull('analytics_events.country')
            ->selectRaw('analytics_events.country AS country, COUNT(DISTINCT analytics_events.visitor) AS visitors')
            ->groupBy('analytics_events.country')
            ->orderByDesc('visitors')
            ->limit($limit);
        if ($this->measuredDays !== []) {
            $this->joinFlags($query)->selectRaw($this->flagCounts(), $this->measuredBindings());
        }

        return $query->get()
            ->mapWithKeys(fn ($row) => [$row->country => [
                'visitors' => (int) $row->visitors,
                'visitors_on_measured_days' => $this->flagFields($row)['visitors_on_measured_days'],
                'confirmed' => $this->flagFields($row)['confirmed_visitors'],
            ]])
            ->all();
    }

    /** What the bot flags caught (everything above leaves these out). */
    private function bots($since): array
    {
        // Not time-on-page notes, nav typing or load pings (never rows, but
        // just in case): people send those and bots do not, so counting them
        // would make the bot share look smaller.
        $row = DB::table('analytics_events')
            ->where('occurred_at', '>=', $since)
            ->whereNotIn('type', [Analytics::PAGE_LEAVE, Analytics::NAV_SEARCH, Analytics::PAGE_PING])
            ->selectRaw('COUNT(*) AS total, SUM(bot > 0) AS flagged,
                SUM((bot & ?) > 0) AS crawler, SUM((bot & ?) > 0) AS no_user_agent,
                SUM((bot & ?) > 0) AS over_daily_cap, SUM((bot & ?) > 0) AS datacenter, SUM((bot & ?) > 0) AS odd_headers,
                SUM((bot & ?) > 0) AS automation', [
                Analytics::BOT_CRAWLER, Analytics::BOT_NO_USER_AGENT, Analytics::BOT_OVER_DAILY_CAP, Analytics::BOT_DATACENTER, Analytics::BOT_HEADERS,
                Analytics::BOT_AUTOMATION,
            ])
            ->first();

        return [
            'all_rows' => (int) $row->total,
            'flagged' => (int) $row->flagged,
            'share' => $row->total > 0 ? round($row->flagged / $row->total, 3) : null,
            'crawler' => (int) $row->crawler,
            'no_user_agent' => (int) $row->no_user_agent,
            'over_daily_cap' => (int) $row->over_daily_cap,
            'datacenter' => (int) $row->datacenter,
            'odd_headers' => (int) $row->odd_headers,
            'automation' => (int) $row->automation,
        ];
    }
}
