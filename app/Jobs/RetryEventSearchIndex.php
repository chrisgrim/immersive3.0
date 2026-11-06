<?php

namespace App\Jobs;

use DateTimeInterface;

/**
 * The same reconcile, for a save that took an exact point out of what the
 * public gets and could not write that to the index: the old document is
 * still searchable, so three tries in a few minutes is not enough. It keeps
 * trying for a day, every half hour once it has settled, and that outlasts a
 * worker stuck on a dead client, which restarts hourly. Never succeeding ends
 * it in failed_jobs.
 */
class RetryEventSearchIndex extends SyncEventSearchIndex
{
    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 1800];

    /** Takes over from $tries: the worker stops counting attempts once this is set. */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }
}
