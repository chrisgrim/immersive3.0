<?php

namespace App\Support\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Throwable;

/**
 * Cookieless first-party analytics. A request only pushes one small JSON
 * note onto a buffer (one Redis RPUSH, under a millisecond); everything
 * slow happens later in ei:analytics-flush: hashing the visitor, flagging
 * bots, writing to MySQL. The raw IP and user agent live only in that
 * buffer, for about a minute, and never reach the database.
 *
 * Recording can never break a page: when disabled it does nothing, and any
 * failure (Redis down, full) is reported once and the note is dropped.
 */
class Analytics
{
    public const SEARCH = 'search';

    public const EVENT_VIEW = 'event_view';

    public const PAGE_VIEW = 'page_view';

    /** Time on page, sent by the browser when the page is hidden (view_id, seconds). */
    public const PAGE_LEAVE = 'page_leave';

    /** What was typed into the nav search (names of events and organizers). */
    public const NAV_SEARCH = 'nav_search';

    /**
     * The browser confirms a page view: sent once the page has loaded and
     * been visible, with whether it reports being automated. Never a row of
     * its own: the flusher marks the page view (js = 1) instead.
     */
    public const PAGE_PING = 'page_ping';

    public const TICKET_CLICK = 'ticket_click';

    public const SEARCH_CLICK = 'search_click';

    /** A search_id: 12 letters and digits, minted per search, sent back on result clicks. */
    public const SEARCH_ID_PATTERN = '/^[A-Za-z0-9]{12}$/';

    // Bot flags, a bitmask on analytics_events.bot. Rows are flagged, never
    // dropped, so reports filter on bot = 0 and can say how much was left out.
    public const BOT_CRAWLER = 1;

    public const BOT_NO_USER_AGENT = 2;

    public const BOT_OVER_DAILY_CAP = 4;

    public const BOT_DATACENTER = 8;

    /**
     * Missing what every real browser sends: an Accept-Language header, or
     * (from a browser that claims to be a recent Chrome, Edge or Firefox)
     * the Sec-Fetch-Site header. Scraper scripts that copy a browser's
     * user agent usually leave these out; they pass the other checks.
     */
    public const BOT_HEADERS = 16;

    /**
     * The page view's browser said it is driven by a script
     * (navigator.webdriver, from the load ping): headless Chrome, Selenium,
     * Playwright and the like.
     */
    public const BOT_AUTOMATION = 32;

    /** The 'array' buffer (tests). */
    private array $memory = [];

    public static function record(string $type, array $data = [], ?Request $request = null): void
    {
        if (! config('analytics.enabled')) {
            return;
        }

        try {
            $request ??= request();

            if (self::optedOut($request)) {
                return;
            }

            app(self::class)->push(json_encode([
                't' => $type,
                'at' => now()->getTimestamp(),
                'ip' => (string) $request->ip(),
                'ua' => mb_substr((string) $request->userAgent(), 0, 512),
                // Whether the browser-only headers came (see BOT_HEADERS).
                'h' => self::headerFlags($request),
                'd' => $data,
            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
        } catch (Throwable $e) {
            // A log line, not report(): every request is a fresh process
            // state, so a Redis outage would file one Sentry event per hit.
            Log::warning('Analytics note dropped: '.$e->getMessage());
        }
    }

    /**
     * Where a page view came from, from the Referer header: a part of this
     * site (search, home, community...), a kind of outside site
     * (search_engine, ai, social, referral), or direct. Only the outside
     * site's host is kept (props.ref), never the full address.
     *
     * @return array{source: string, ref: ?string}
     */
    public static function referrer(Request $request): array
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return ['source' => 'direct', 'ref' => null];
        }

        $host = strtolower(preg_replace('/^www\./i', '', $host));
        $ours = strtolower(preg_replace('/^www\./i', '', (string) $request->getHost()));

        if ($host === $ours) {
            $path = (string) parse_url((string) $request->headers->get('referer'), PHP_URL_PATH);
            $source = match (true) {
                $path === '' || $path === '/' => 'home',
                str_starts_with($path, '/index/search') => 'search',
                str_starts_with($path, '/communities') => 'community',
                str_starts_with($path, '/organizers') => 'organizer',
                str_starts_with($path, '/events') => 'event',
                default => 'site',
            };

            return ['source' => $source, 'ref' => null];
        }

        $kinds = [
            'search_engine' => '/(^|\.)(google|bing|duckduckgo|yahoo|ecosia|baidu|yandex|brave|startpage|qwant)\./',
            'ai' => '/(^|\.)(chatgpt\.com|openai\.com|perplexity\.ai|claude\.ai|gemini\.google\.com|copilot\.microsoft\.com)$/',
            'social' => '/(^|\.)(facebook|instagram|reddit|bsky|twitter|threads|tiktok|youtube|linkedin|pinterest)\.|(^|\.)(t\.co|x\.com|lnkd\.in)$/',
        ];

        // AI first: gemini.google.com is not a search engine visit.
        foreach (['ai', 'search_engine', 'social'] as $kind) {
            if (preg_match($kinds[$kind], $host)) {
                return ['source' => $kind, 'ref' => mb_substr($host, 0, 100)];
            }
        }

        return ['source' => 'referral', 'ref' => mb_substr($host, 0, 100)];
    }

    /**
     * The same bot test the flusher applies (crawler user agent, none at
     * all, or a cloud network), for the few places that must decide during
     * the request, like whether a ticket click counts for the organizer.
     */
    public static function looksLikeBot(Request $request): bool
    {
        $ua = trim((string) $request->userAgent());
        if ($ua === '' || (new CrawlerDetect)->isCrawler($ua) || self::missingBrowserHeaders($ua, self::headerFlags($request))) {
            return true;
        }

        $asn = app(GeoLookup::class)->asn((string) $request->ip());

        return $asn !== null && in_array($asn, config('analytics.hosting_asns'), true);
    }

    /** Whether the browser-only headers came (see BOT_HEADERS). */
    public static function headerFlags(Request $request): array
    {
        return [
            'al' => trim((string) $request->headers->get('accept-language')) !== '',
            'sf' => $request->headers->has('sec-fetch-site'),
        ];
    }

    /**
     * BOT_HEADERS: a real browser always sends Accept-Language, and a recent
     * Chrome, Edge or Firefox (Chrome 80+, Firefox 90+) also Sec-Fetch-Site.
     * Safari is not held to the second (it only added it in 16.4). Notes from
     * before headers were recorded ($headers null) are never flagged.
     */
    public static function missingBrowserHeaders(string $ua, $headers): bool
    {
        if (! is_array($headers)) {
            return false;
        }

        if (empty($headers['al'])) {
            return true;
        }

        $recent = (preg_match('~(?:Chrome|Chromium)/(\d+)~', $ua, $chrome) && (int) $chrome[1] >= 80)
            || (preg_match('~Firefox/(\d+)~', $ua, $firefox) && (int) $firefox[1] >= 90);

        return $recent && empty($headers['sf']);
    }

    /**
     * Where ei:analytics-capture keeps its switches: a small file, not the
     * cache (Redis evicts any key when full, which would quietly switch a
     * capture back to its .env default).
     */
    public static function overridesPath(): string
    {
        return config('analytics.capture_file') ?: storage_path('app/analytics-capture.json');
    }

    /** @var array<string, bool>|null overrides, read once per request */
    private ?array $overrides = null;

    /**
     * Is this phase 2 capture switched on? An override set by
     * ei:analytics-capture (no deploy needed) wins over config; a missing or
     * unreadable override file falls back to config.
     */
    public static function captures(string $name): bool
    {
        if (! config('analytics.enabled')) {
            return false;
        }

        $analytics = app(self::class);
        if ($analytics->overrides === null) {
            $file = @file_get_contents(self::overridesPath());
            $analytics->overrides = is_string($file) && is_array($decoded = json_decode($file, true)) ? $decoded : [];
        }

        return (bool) ($analytics->overrides[$name] ?? config("analytics.capture.{$name}", false));
    }

    /** Forget the per-request override memo (tests, and the command). */
    /**
     * Is this capture off now, but did it record something in the last 13
     * months (however it was switched off: the command, .env or config)?
     * The privacy page keeps naming it until those records are gone. Read
     * from the daily totals through their (dim, day) index; cached an hour.
     */
    public static function recentlyCaptured(string $name): bool
    {
        if (self::captures($name)) {
            return false;
        }

        $since = now()->subDays(max(30, (int) config('analytics.raw_days', 395)))->toDateString();

        try {
            return self::recordedSince($name, $since);
        } catch (Throwable $e) {
            // The privacy page must not fail over this.
            report($e);

            return false;
        }
    }

    private static function recordedSince(string $name, string $since): bool
    {
        $key = "analytics:recently:{$name}";
        $cached = Cache::get($key);
        if ($cached !== null) {
            return (bool) $cached;
        }

        // The daily totals, plus the last two days of raw rows (not totalled
        // yet if the capture was only on briefly, or a rollup is failing).
        $daily = DB::table('analytics_daily')->where('bot', 0)->where('day', '>=', $since);
        $raw = DB::table('analytics_events')->where('bot', 0)->where('occurred_at', '>=', now()->subDays(2));
        [$daily, $raw] = match ($name) {
            'page_views' => [$daily->where('dim', 'path'), $raw->where('type', self::PAGE_VIEW)],
            'device' => [$daily->where('dim', 'device'), $raw->whereNotNull('device')],
            'city' => [$daily->where('dim', 'city'), $raw->whereNotNull('city')],
            'utm' => [$daily->whereIn('dim', ['utm_source', 'utm_medium', 'utm_campaign']), $raw->where(fn ($q) => $q->whereNotNull('utm_source')->orWhereNotNull('utm_medium')->orWhereNotNull('utm_campaign'))],
            'duration' => [$daily->where('dim', 'all')->where('seconds_count', '>', 0), $raw->where('type', self::PAGE_LEAVE)],
            'nav_search' => [$daily->where('dim', 'all')->where('type', self::NAV_SEARCH), $raw->where('type', self::NAV_SEARCH)],
            'js_ping' => self::hasConfirmationColumns()
                ? [$daily->where('dim', 'all')->whereNotNull('js_visitors'), $raw->where('type', self::PAGE_VIEW)->whereNotNull('js')]
                : [null, null],
            default => [null, null],
        };

        $found = $daily !== null && ($daily->exists() || $raw->exists());
        // Found stays true for an hour; not found is asked again in 5 minutes.
        Cache::put($key, $found, $found ? now()->addHour() : now()->addMinutes(5));

        return $found;
    }

    /**
     * Rows the server saw itself, in the request that made them: what makes
     * someone a visitor. Beacons (time on page, result clicks) arrive later
     * as plain POSTs, maybe after midnight UTC under the next day's visitor
     * code, so they only ever count for the visitor of their view or search.
     */
    public const SERVER_TYPES = [self::PAGE_VIEW, self::EVENT_VIEW, self::SEARCH, self::TICKET_CLICK, self::NAV_SEARCH];

    /**
     * Per visitor flags over a range of days (the visitor code changes
     * daily, so a visitor is a visitor-day), as a query of (visitor,
     * automated, js, engaged). The daily totals and the admin report count
     * them the same way:
     *
     * - automated: any of the day's rows carries BOT_AUTOMATION (its browser
     *   said it is driven by a script); never confirmed, never engaged.
     * - js (browser confirmed): one of the visitor's people page views was
     *   pinged by a browser that had shown it. Only the ping counts: any
     *   other beacon is a plain POST a script can send.
     * - engaged (as GA4 counts it, and only when confirmed): a page on
     *   screen 10+ seconds, a ticket click, a search result click, nav
     *   typing or a typed search, or two or more page views.
     *
     * Every row counts, bots included (an automated flag marks the whole
     * day). A time-on-page note counts for its page view's visitor and a
     * result click for its search's (view_id and search_id indexes), up to
     * a day after $to, like the rollup's time on page: a leave at 00:02 UTC
     * belongs to the 23:58 view, not to a visitor of its own.
     *
     * @return array{0: string, 1: array} SQL and bindings ($to null: open-ended)
     */
    public static function visitorFlagsQuery(string $from, ?string $to = null): array
    {
        $in = fn (string ...$types) => "e.type IN ('".implode("', '", $types)."')";
        $automated = 'MAX((e.bot & '.self::BOT_AUTOMATION.') > 0)';
        $confirmed = "(MAX(e.bot = 0 AND e.type = '".self::PAGE_VIEW."' AND e.js = 1) AND NOT {$automated})";
        $active = "(MAX(e.bot = 0 AND ((e.type = '".self::PAGE_LEAVE."' AND e.seconds >= 10) OR ".$in(self::TICKET_CLICK, self::SEARCH_CLICK, self::NAV_SEARCH)."
                OR (e.type = '".self::SEARCH."' AND e.source = 'list'))) OR SUM(e.bot = 0 AND ".$in(self::PAGE_VIEW, self::EVENT_VIEW).') >= 2)';
        // Looked up only for the beacon rows (the CASE), each through its
        // own index: a join here let MySQL scan every search per row.
        $owner = "COALESCE(CASE e.type
                WHEN '".self::PAGE_LEAVE."' THEN (SELECT v.visitor FROM analytics_events v FORCE INDEX (analytics_events_view_id_index)
                    WHERE v.view_id = e.view_id AND v.type = '".self::PAGE_VIEW."' LIMIT 1)
                WHEN '".self::SEARCH_CLICK."' THEN (SELECT s.visitor FROM analytics_events s FORCE INDEX (analytics_events_search_id_index)
                    WHERE s.search_id = e.search_id AND s.type = '".self::SEARCH."' LIMIT 1)
            END, e.visitor)";
        $range = $to === null ? '' : ' AND (e.occurred_at < ? OR ('.$in(self::PAGE_LEAVE, self::SEARCH_CLICK).' AND e.occurred_at < ? + INTERVAL 1 DAY))';

        return ["SELECT {$owner} AS visitor, {$automated} AS automated, {$confirmed} AS js, ({$confirmed} AND {$active}) AS engaged
            FROM analytics_events e
            WHERE e.occurred_at >= ?{$range}
            GROUP BY 1", $to === null ? [$from] : [$from, $to, $to]];
    }

    /** @var bool|null whether the browser confirmation columns exist, asked once per process */
    private ?bool $confirmationColumns = null;

    /**
     * Whether the migration adding analytics_events.js and
     * analytics_daily.js_visitors / engaged_visitors has run. Between a
     * deploy's rsync and its migrate (or if that migration gave up at its
     * lock timeout) they are missing, and the flusher, rollup and readers
     * must neither write nor read them. Asked once per process.
     */
    public static function hasConfirmationColumns(): bool
    {
        $analytics = app(self::class);

        return $analytics->confirmationColumns ??= (function () {
            try {
                return Schema::hasColumn('analytics_events', 'js')
                    && Schema::hasColumns('analytics_daily', ['js_visitors', 'engaged_visitors']);
            } catch (Throwable) {
                return false;
            }
        })();
    }

    /** Tests: pretend the columns are (or are not) there; null asks again. */
    public function assumeConfirmationColumns(?bool $present): void
    {
        $this->confirmationColumns = $present;
    }

    public function forgetOverrides(): void
    {
        $this->overrides = null;
    }

    /**
     * The visitor's browser asks not to be tracked: Global Privacy Control
     * (Sec-GPC: 1) or Do Not Track (DNT: 1). Nothing of theirs is recorded.
     */
    public static function optedOut(Request $request): bool
    {
        return $request->headers->get('sec-gpc') === '1' || $request->headers->get('dnt') === '1';
    }

    /**
     * Campaign tags on a landing: utm_source / utm_medium / utm_campaign, or
     * ?ref= (what NoProscenium and others send) as the source. Lowercased,
     * only letters, digits and . _ - and spaces, 64 characters each.
     *
     * @return array{source?: string, medium?: string, campaign?: string}
     */
    public static function campaign(Request $request): array
    {
        $clean = function ($value): ?string {
            if (! is_string($value)) {
                return null;
            }
            $value = trim(preg_replace('/[^a-z0-9._ -]+/', '', mb_strtolower($value)));

            return $value === '' ? null : mb_substr($value, 0, 64);
        };

        return array_filter([
            'source' => $clean($request->query('utm_source')) ?? $clean($request->query('ref')),
            'medium' => $clean($request->query('utm_medium')),
            'campaign' => $clean($request->query('utm_campaign')),
        ]);
    }

    /** A browser prefetch or prerender, not a person looking at the page. */
    public static function isPrefetch(Request $request): bool
    {
        $purpose = strtolower((string) ($request->headers->get('sec-purpose') ?? $request->headers->get('purpose')));

        return str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender');
    }

    public function push(string $note): void
    {
        if (config('analytics.buffer') === 'array') {
            $this->memory[] = $note;

            return;
        }

        $key = config('analytics.buffer_key');
        $max = (int) config('analytics.buffer_max');

        // The list expires a day after it was started (NX: a push never
        // pushes the expiry back), so if the flusher stops running the raw
        // IPs in it do not outlive the day. The flusher empties it every
        // minute, which deletes it; the next push starts a fresh day.
        Redis::pipeline(function ($pipe) use ($key, $note, $max) {
            $pipe->rpush($key, $note);
            $pipe->ltrim($key, -$max, -1);
            $pipe->expire($key, 86400, 'NX');
        });
    }

    /**
     * Take up to $count notes off the front of the buffer, in one atomic
     * step (LPOP with a count), so pushes landing meanwhile cannot shift
     * what is removed. If writing them fails, putBack() returns them.
     *
     * @return string[]
     */
    public function pop(int $count): array
    {
        if (config('analytics.buffer') === 'array') {
            return array_splice($this->memory, 0, $count);
        }

        return Redis::lpop(config('analytics.buffer_key'), $count) ?: [];
    }

    /** Return popped notes to the front of the buffer, in their order. */
    public function putBack(array $notes): void
    {
        // A note older than a day is dropped, not kept: the buffer holds
        // raw IPs, which must not outlive the day however often a flush
        // fails and puts its notes back.
        $notes = array_values(array_filter($notes, function ($note) {
            $at = json_decode($note, true)['at'] ?? null;

            return is_int($at) && $at > now()->getTimestamp() - 86400;
        }));
        if ($notes === []) {
            return;
        }

        if (config('analytics.buffer') === 'array') {
            array_unshift($this->memory, ...$notes);

            return;
        }

        // LPOP may have emptied (and so deleted) the list, taking its expiry
        // with it: set one if it has none (NX keeps an existing one).
        Redis::pipeline(function ($pipe) use ($notes) {
            $pipe->lpush(config('analytics.buffer_key'), ...array_reverse($notes));
            $pipe->expire(config('analytics.buffer_key'), 86400, 'NX');
        });
    }

    public const LIVE_KEY = 'analytics:live';

    /**
     * "On the site now": people (not bots) with a page view in the last
     * $minutes, from a Redis sorted set the flusher keeps (visitor => time
     * of last page view). Entries older than 10 minutes are trimmed.
     *
     * @param  array<string, int>  $visitors
     */
    public function markLive(array $visitors): void
    {
        $cutoff = now()->subMinutes(10)->getTimestamp();

        if (config('analytics.buffer') === 'array') {
            $this->liveSet = array_filter($visitors + $this->liveSet, fn ($at) => $at >= $cutoff);

            return;
        }

        Redis::pipeline(function ($pipe) use ($visitors, $cutoff) {
            foreach ($visitors as $visitor => $at) {
                $pipe->zadd(self::LIVE_KEY, $at, $visitor);
            }
            $pipe->zremrangebyscore(self::LIVE_KEY, '-inf', $cutoff);
            $pipe->expire(self::LIVE_KEY, 900);
        });
    }

    public function liveCount(int $minutes = 5): int
    {
        $since = now()->subMinutes($minutes)->getTimestamp();

        if (config('analytics.buffer') === 'array') {
            return count(array_filter($this->liveSet, fn ($at) => $at >= $since));
        }

        return (int) Redis::zcount(self::LIVE_KEY, $since, '+inf');
    }

    /** @var array<string, int> the 'array' live set (tests) */
    private array $liveSet = [];

    /** Throw the whole buffer away (analytics switched off: no raw IPs kept). */
    public function clear(): void
    {
        if (config('analytics.buffer') === 'array') {
            $this->memory = [];

            return;
        }

        Redis::del(config('analytics.buffer_key'));
    }
}
