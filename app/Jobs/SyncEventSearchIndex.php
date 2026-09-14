<?php

namespace App\Jobs;

use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * "Reconcile event N with the search index": re-read the row when the job
 * RUNS and index it if it is live and published, otherwise remove it. The
 * decision is deliberately not made at dispatch time: with retries and
 * backoff, an "index" job queued before a delete could otherwise run after
 * the delete and resurrect the document.
 *
 * Backoff covers the known Elasticsearch gap after the nightly reboot
 * (~55 s): a job that fails during it retries at 30 s and 120 s before
 * landing in failed_jobs (see CLAUDE.md for queue:retry).
 */
class SyncEventSearchIndex implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public int $eventId) {}

    public function handle(): void
    {
        Event::reconcileSearchIndex($this->eventId);
    }
}
