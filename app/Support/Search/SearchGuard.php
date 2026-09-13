<?php

namespace App\Support\Search;

use Closure;
use Elastic\Elasticsearch\Exception\ProductCheckException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\TransportException;
use Throwable;

/**
 * Wraps an Elasticsearch read so an unreachable or erroring cluster degrades
 * the page (empty results, a "search unavailable" flag) instead of a 500.
 * Elasticsearch is restarted by the nightly apt window and has a known
 * ~55 s NoNodeAvailable gap after the auto-reboot; the search page and the
 * nav autocomplete used to 500 for that whole window.
 *
 * Only cluster failures are caught: no node reachable (transport), a 5xx
 * from the cluster, or the product check failing. A 4xx is a bad query,
 * which is our bug and still propagates so it shows up as an error.
 *
 * After the first failure in a request every later read short-circuits to
 * its fallback: the listings page makes three reads per view, and during an
 * outage each would otherwise wait out its own connection attempt and file
 * its own Sentry event. The memo lives on the current Request object, so
 * it cannot outlive one request (or one test).
 */
final class SearchGuard
{
    private const MEMO = 'search_guard.unavailable';

    /**
     * Run $query; on a search-cluster failure report it (Sentry) and return
     * $fallback (a Closure is invoked). Any other exception propagates.
     */
    public static function run(callable $query, mixed $fallback = null): mixed
    {
        if (self::unavailable()) {
            return self::resolve($fallback);
        }

        try {
            return $query();
        } catch (Throwable $e) {
            if (! self::isSearchFailure($e)) {
                throw $e;
            }

            self::markUnavailable();
            report($e);

            return self::resolve($fallback);
        }
    }

    public static function isSearchFailure(Throwable $e): bool
    {
        return $e instanceof TransportException
            || $e instanceof ServerResponseException
            || $e instanceof ProductCheckException;
    }

    /** Forget a failure seen earlier in this request (tests). */
    public static function reset(): void
    {
        if (app()->bound('request')) {
            request()->attributes->remove(self::MEMO);
        }
    }

    private static function unavailable(): bool
    {
        return app()->bound('request') && request()->attributes->get(self::MEMO) === true;
    }

    private static function markUnavailable(): void
    {
        if (app()->bound('request')) {
            request()->attributes->set(self::MEMO, true);
        }
    }

    private static function resolve(mixed $fallback): mixed
    {
        return $fallback instanceof Closure ? $fallback() : $fallback;
    }
}
