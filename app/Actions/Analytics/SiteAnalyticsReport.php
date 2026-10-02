<?php

namespace App\Actions\Analytics;

use App\Models\Event;
use App\Support\Analytics\Analytics;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The first-party analytics summary behind the admin Analytics page and the
 * get-site-analytics MCP tool: one shape, so both always agree. Humans only
 * (bot = 0) except the `bots` section, which says how much was filtered.
 * Reads analytics_events directly; at ~1M rows a year every query here is
 * an index range scan on (type, occurred_at).
 */
class SiteAnalyticsReport
{
    public const MAX_DAYS = 395;

    private const LIMIT = 25;

    private const FILTER_PATHS = "'$.categories', '$.tags', '$.start', '$.priceMin', '$.priceMax', '$.remoteLocation'";

    public function handle(int $days = 30): array
    {
        $days = max(1, min(self::MAX_DAYS, $days));
        $since = now()->subDays($days);

        return [
            'days' => $days,
            'since' => $since->toIso8601String(),
            'totals' => $this->totals($since),
            'searches' => $this->searches($since),
            'zero_result_searches' => $this->zeroResultSearches($since),
            'events' => $this->events($since),
            'view_sources' => $this->viewSources($since),
            'search_clicks' => $this->searchClicks($since),
            'countries' => $this->countries($since),
            'bots' => $this->bots($since),
        ];
    }

    private function rows($since, ?string $type = null): Builder
    {
        return DB::table('analytics_events')
            ->when($type, fn ($query) => $query->where('type', $type))
            ->where('occurred_at', '>=', $since)
            ->where('bot', 0);
    }

    /** Per type: how many, and by how many different visitors. */
    private function totals($since): array
    {
        return $this->rows($since)
            ->selectRaw('type, COUNT(*) AS total, COUNT(DISTINCT visitor) AS visitors')
            ->groupBy('type')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->type => ['total' => (int) $row->total, 'visitors' => (int) $row->visitors]])
            ->all();
    }

    /** The places people search most, and how often each came back empty. */
    private function searches($since): array
    {
        return $this->rows($since, Analytics::SEARCH)
            ->whereNotNull('query')
            ->where('query', '!=', '')
            ->selectRaw('query, COUNT(*) AS searches, SUM(results = 0) AS found_nothing')
            ->groupBy('query')
            ->orderByDesc('searches')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn ($row) => ['place' => $row->query, 'searches' => (int) $row->searches, 'found_nothing' => (int) $row->found_nothing])
            ->all();
    }

    /**
     * Searches that found nothing, by place: the gaps worth filling. Split
     * into "with filters" (the filters may be why) and plain.
     */
    private function zeroResultSearches($since): array
    {
        return $this->rows($since, Analytics::SEARCH)
            ->where('results', 0)
            ->selectRaw("COALESCE(NULLIF(query, ''), '(no place)') AS place, COUNT(*) AS searches,
                SUM(JSON_CONTAINS_PATH(COALESCE(props, '{}'), 'one', ".self::FILTER_PATHS.')) AS with_filters,
                COUNT(DISTINCT visitor) AS visitors, MAX(occurred_at) AS last_searched')
            ->groupBy('place')
            ->orderByDesc('searches')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn ($row) => [
                'place' => $row->place,
                'searches' => (int) $row->searches,
                'with_filters' => (int) $row->with_filters,
                'visitors' => (int) $row->visitors,
                'last_searched' => $row->last_searched,
            ])
            ->all();
    }

    /** The most viewed events, with their ticket clicks and click-through. */
    private function events($since): array
    {
        $counts = $this->rows($since)
            ->whereIn('type', [Analytics::EVENT_VIEW, Analytics::TICKET_CLICK])
            ->whereNotNull('event_id')
            ->selectRaw('event_id, SUM(type = ?) AS views, SUM(type = ?) AS ticket_clicks', [Analytics::EVENT_VIEW, Analytics::TICKET_CLICK])
            ->groupBy('event_id')
            ->orderByDesc('views')
            ->limit(self::LIMIT)
            ->get();

        $events = Event::withoutGlobalScopes()->withTrashed()
            ->whereIn('id', $counts->pluck('event_id'))
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        return $counts->map(fn ($row) => [
            'event_id' => (int) $row->event_id,
            'name' => $events[$row->event_id]->name ?? null,
            'slug' => $events[$row->event_id]->slug ?? null,
            'views' => (int) $row->views,
            'ticket_clicks' => (int) $row->ticket_clicks,
            'click_through' => $row->views > 0 ? round($row->ticket_clicks / $row->views, 3) : null,
        ])->all();
    }

    /** Where event page views came from (Analytics::referrer), and the top outside sites. */
    private function viewSources($since): array
    {
        $sources = $this->rows($since, Analytics::EVENT_VIEW)
            ->selectRaw('source, COUNT(*) AS views')
            ->groupBy('source')
            ->orderByDesc('views')
            ->pluck('views', 'source')
            ->map(fn ($views) => (int) $views)
            ->all();

        $sites = $this->rows($since, Analytics::EVENT_VIEW)
            ->whereNotNull('props')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(props, '$.ref')) AS site, COUNT(*) AS views")
            ->groupBy('site')
            ->havingRaw('site IS NOT NULL')
            ->orderByDesc('views')
            ->limit(self::LIMIT)
            ->pluck('views', 'site')
            ->map(fn ($views) => (int) $views)
            ->all();

        return ['by_kind' => $sources, 'outside_sites' => $sites];
    }

    /** How often a search leads to a result click, and where in the list the clicks land. */
    private function searchClicks($since): array
    {
        $searches = $this->rows($since, Analytics::SEARCH)->whereNotNull('search_id')->count();
        $clicked = $this->rows($since, Analytics::SEARCH_CLICK)->distinct()->count('search_id');

        $positions = $this->rows($since, Analytics::SEARCH_CLICK)
            ->selectRaw("LEAST(CAST(JSON_EXTRACT(props, '$.position') AS UNSIGNED), 11) AS position, COUNT(*) AS clicks")
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

    /** Visitors by country. */
    private function countries($since): array
    {
        return $this->rows($since)
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(DISTINCT visitor) AS visitors')
            ->groupBy('country')
            ->orderByDesc('visitors')
            ->limit(15)
            ->pluck('visitors', 'country')
            ->map(fn ($visitors) => (int) $visitors)
            ->all();
    }

    /** What the bot flags caught (everything above leaves these out). */
    private function bots($since): array
    {
        $row = DB::table('analytics_events')
            ->where('occurred_at', '>=', $since)
            ->selectRaw('COUNT(*) AS total, SUM(bot > 0) AS flagged,
                SUM((bot & ?) > 0) AS crawler, SUM((bot & ?) > 0) AS no_user_agent,
                SUM((bot & ?) > 0) AS over_daily_cap, SUM((bot & ?) > 0) AS datacenter', [
                Analytics::BOT_CRAWLER, Analytics::BOT_NO_USER_AGENT, Analytics::BOT_OVER_DAILY_CAP, Analytics::BOT_DATACENTER,
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
        ];
    }
}
