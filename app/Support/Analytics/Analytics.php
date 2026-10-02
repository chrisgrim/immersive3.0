<?php

namespace App\Support\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
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

    // Bot flags, a bitmask on analytics_events.bot. Rows are flagged, never
    // dropped, so reports filter on bot = 0 and history can be re-scored.
    public const BOT_CRAWLER = 1;

    public const BOT_NO_USER_AGENT = 2;

    public const BOT_OVER_DAILY_CAP = 4;

    /** The 'array' buffer (tests). */
    private array $memory = [];

    private bool $reported = false;

    public static function record(string $type, array $data = [], ?Request $request = null): void
    {
        if (! config('analytics.enabled')) {
            return;
        }

        $analytics = app(self::class);

        try {
            $request ??= request();

            $analytics->push(json_encode([
                't' => $type,
                'at' => now()->getTimestamp(),
                'ip' => (string) $request->ip(),
                'ua' => mb_substr((string) $request->userAgent(), 0, 512),
                'd' => $data,
            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
        } catch (Throwable $e) {
            // Once per process: an outage must not file one Sentry event per hit.
            if (! $analytics->reported) {
                $analytics->reported = true;
                report($e);
            }
        }
    }

    public function push(string $note): void
    {
        if (config('analytics.buffer') === 'array') {
            $this->memory[] = $note;

            return;
        }

        $key = config('analytics.buffer_key');
        $max = (int) config('analytics.buffer_max');

        Redis::pipeline(function ($pipe) use ($key, $note, $max) {
            $pipe->rpush($key, $note);
            $pipe->ltrim($key, -$max, -1);
        });
    }

    /**
     * Take up to $count notes off the front of the buffer.
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
}
