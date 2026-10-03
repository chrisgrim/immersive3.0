<?php

namespace App\Support\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
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
        if ($ua === '' || (new CrawlerDetect)->isCrawler($ua)) {
            return true;
        }

        $asn = app(GeoLookup::class)->asn((string) $request->ip());

        return $asn !== null && in_array($asn, config('analytics.hosting_asns'), true);
    }

    /**
     * The visitor's browser asks not to be tracked: Global Privacy Control
     * (Sec-GPC: 1) or Do Not Track (DNT: 1). Nothing of theirs is recorded.
     */
    public static function optedOut(Request $request): bool
    {
        return $request->headers->get('sec-gpc') === '1' || $request->headers->get('dnt') === '1';
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

        // The list expires a day after the last push: if the flusher stops
        // running, the raw IPs in it do not outlive the day.
        Redis::pipeline(function ($pipe) use ($key, $note, $max) {
            $pipe->rpush($key, $note);
            $pipe->ltrim($key, -$max, -1);
            $pipe->expire($key, 86400);
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
        if ($notes === []) {
            return;
        }

        if (config('analytics.buffer') === 'array') {
            array_unshift($this->memory, ...$notes);

            return;
        }

        // LPOP may have emptied (and so deleted) the list, taking its expiry
        // with it: set it again so returned notes still expire.
        Redis::pipeline(function ($pipe) use ($notes) {
            $pipe->lpush(config('analytics.buffer_key'), ...array_reverse($notes));
            $pipe->expire(config('analytics.buffer_key'), 86400);
        });
    }

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
