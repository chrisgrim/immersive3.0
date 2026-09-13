<?php

namespace App\Support\Search;

use Closure;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\TransportException;
use Throwable;

/**
 * Wraps an Elasticsearch read so an unreachable or erroring cluster degrades
 * the page (empty results, a "search unavailable" flag) instead of a 500.
 * Elasticsearch is restarted by the nightly apt window and has a known
 * ~55 s NoNodeAvailable gap after the auto-reboot; the search page and the
 * nav autocomplete used to 500 for that whole window.
 */
final class SearchGuard
{
    /**
     * Run $query; on a search-cluster failure report it (Sentry) and return
     * $fallback (a Closure is invoked). Any other exception propagates.
     */
    public static function run(callable $query, mixed $fallback = null): mixed
    {
        try {
            return $query();
        } catch (Throwable $e) {
            if (! self::isSearchFailure($e)) {
                throw $e;
            }

            report($e);

            return $fallback instanceof Closure ? $fallback() : $fallback;
        }
    }

    public static function isSearchFailure(Throwable $e): bool
    {
        return $e instanceof ElasticsearchException || $e instanceof TransportException;
    }
}
