<?php

namespace App\Console\Commands;

use App\Actions\Search\EventSearchFilterBuilder;
use App\Models\Event;
use App\Models\Events\RemoteLocation;
use App\Models\SavedSearch;
use App\Notifications\SavedSearchNewEventsNotification;
use Carbon\Carbon;
use Elastic\ScoutDriverPlus\Support\Query;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The saved-search "notify me about new events" pilot (chgrim@gmail.com
 * only — see config('features.saved_search_notifications_user')). Runs
 * twice daily (see ScheduleServiceProvider). Cursor design deliberately
 * avoids storing a result-set snapshot: each saved search just tracks
 * last_checked_at, and this command asks Elasticsearch for events matching
 * that search's criteria with `published_at` in (last_checked_at, cutoff] —
 * "new" means newly PUBLISHED since the last check, not "an existing event
 * that started matching because it was edited." That's the deliberate scope
 * of a timestamp-cursor design; see EventSearchFilterBuilder's own docblock
 * for why matching itself isn't shared with the live search page.
 */
class NotifySavedSearchMatchesCommand extends Command
{
    protected $signature = 'ei:notify-saved-searches';

    protected $description = 'Email the saved-search notification pilot user about newly published events matching their enabled searches';

    /**
     * Capped, not unbounded — a saved search broad enough to match hundreds
     * of newly-published events in one window is the exception, not the
     * case this pilot needs to handle gracefully. The email template shows
     * the first 5 individually and "and N more" beyond that; this cap is
     * just the outer bound on what gets fetched/serialized into the queued
     * notification at all. Hitting the cap does NOT drop the remainder —
     * see checkOne()'s cursor-advancement comment — it just spreads a large
     * backlog across more of this command's twice-daily runs instead of
     * emailing it all in one shot.
     */
    private const MAX_EVENTS_PER_EMAIL = 50;

    public function handle(EventSearchFilterBuilder $filterBuilder)
    {
        // withoutOverlapping() on the schedule entry (ScheduleServiceProvider)
        // only mutex-protects invocations that go through `schedule:run` —
        // it does nothing for a manual `php artisan ei:notify-saved-searches`
        // run over SSH (caught in review), which could overlap the 8am/8pm
        // tick closely enough for both to read the same stale last_checked_at
        // and double-email the pilot user for the same matches. Non-blocking
        // (get(), not block()) — if a run is already in progress, skip this
        // one entirely rather than queueing behind it; the next scheduled
        // tick will cover whatever this one would have.
        $ran = Cache::lock('ei:notify-saved-searches', 300)->get(function () use ($filterBuilder) {
            $this->process($filterBuilder);
        });

        if (! $ran) {
            $this->warn('Another run is already in progress — skipping.');
        }

        return Command::SUCCESS;
    }

    private function process(EventSearchFilterBuilder $filterBuilder): void
    {
        $pilotEmail = config('features.saved_search_notifications_user');

        // One cutoff for the ENTIRE run, captured once — not recomputed per
        // search. Every search in this run checks the exact same window, so
        // one that's slow to process doesn't get a slightly later window
        // than the one before it. The 5-minute trail protects against
        // Elasticsearch's own indexing/refresh delay: an event published
        // seconds before this command runs might not be searchable yet, and
        // without this margin a slow index update could let it fall behind
        // an already-advanced cursor and never get picked up at all.
        $cutoff = Carbon::now()->subMinutes(5);

        $searches = SavedSearch::where('notify_new_events', true)
            ->whereHas('user', fn ($query) => $query->where('email', $pilotEmail))
            ->with('user')
            ->get();

        $this->info("Checking {$searches->count()} saved search(es) with notifications enabled (cutoff {$cutoff->toDateTimeString()})");

        foreach ($searches as $savedSearch) {
            try {
                $this->checkOne($savedSearch, $filterBuilder, $cutoff);
            } catch (\Throwable $e) {
                // checkOne()'s own try/catch only guards the ES query
                // (deliberately, so a query failure leaves the cursor
                // untouched for a clean retry next run) — this outer one
                // guards everything else in it: the notify() dispatch and
                // the final cursor update (caught in review). Without it,
                // one search throwing here (e.g. a transient DB error
                // writing to the notifications/jobs table) would escape
                // this loop entirely and skip every OTHER enabled search in
                // this run — not just the one that failed.
                $this->error("Saved search {$savedSearch->id}: unexpected error, skipping — {$e->getMessage()}");
            }
        }
    }

    private function checkOne(SavedSearch $savedSearch, EventSearchFilterBuilder $filterBuilder, Carbon $cutoff): void
    {
        // Null means never checked — shouldn't normally happen (the toggle
        // endpoint sets this the moment notifications are enabled), but if
        // it does, treat it as "just start now" rather than dumping
        // whatever's published since the beginning of time.
        $lastCheckedAt = $savedSearch->last_checked_at ?? $cutoff;

        if ($lastCheckedAt->greaterThanOrEqualTo($cutoff)) {
            $savedSearch->update(['last_checked_at' => $cutoff]);
            $this->info("Saved search {$savedSearch->id} ({$savedSearch->name}): window not open yet, cursor advanced");

            return;
        }

        $criteria = $this->criteriaFromSavedSearch($savedSearch);
        $filters = $filterBuilder->buildFilters($criteria);

        $query = Query::bool()
            ->filter(Query::range()->field('closingDate')->gte('now/d'))
            ->filter(
                Query::range()
                    ->field('published_at')
                    ->gt($lastCheckedAt->toDateTimeString())
                    ->lte($cutoff->toDateTimeString())
            );
        $filterBuilder->applyToQuery($query, $filters, $criteria);
        if ($filters['prices'] ?? null) {
            $query->filter($filters['prices']);
        }

        try {
            // Ascending, not descending — this is what makes the cursor
            // below correct. More than MAX_EVENTS_PER_EMAIL new matches in
            // one window used to be silently, permanently lost: the query
            // (sorted newest-first) only ever fetched the newest 50, but the
            // cursor still jumped all the way to the end of the window
            // regardless, so the older excluded matches fell behind the
            // cursor and would never be found by any future run either
            // (Codex caught this in review). Sorting oldest-first and
            // advancing the cursor only past what was actually fetched
            // means a backlog over the cap just takes an extra run or two
            // to fully drain, instead of vanishing.
            $events = Event::searchQuery($query)
                ->sort('published_at', 'asc')
                ->size(self::MAX_EVENTS_PER_EMAIL)
                ->execute()
                ->models();
        } catch (\Throwable $e) {
            // Cursor NOT advanced — a transient Elasticsearch failure should
            // retry the same window next run, not silently skip it.
            $this->error("Saved search {$savedSearch->id}: query failed, cursor not advanced — {$e->getMessage()}");

            return;
        }

        $newCursor = $this->determineNewCursor($events, $cutoff);

        if ($events->isNotEmpty()) {
            // Newest-first for the email/feed display — matches how this
            // read before the query itself switched to ascending above; the
            // sort order used to decide the cursor and the order shown to
            // the user are deliberately decoupled.
            $displayEvents = $events->sortByDesc('published_at')->values();
            $savedSearch->user->notify(new SavedSearchNewEventsNotification($savedSearch, $displayEvents));
            $this->info("Saved search {$savedSearch->id} ({$savedSearch->name}): {$events->count()} new match(es), notified {$savedSearch->user->email}");
        } else {
            $this->info("Saved search {$savedSearch->id} ({$savedSearch->name}): no new matches");
        }

        // Advanced only after the notification was successfully dispatched
        // (queued) above — matches this app's existing queued-notification
        // convention (see SavedEventNewDatesNotification's own comment on
        // the same "queued, not delivered" distinction).
        $savedSearch->update(['last_checked_at' => $newCursor]);
    }

    /**
     * Public (not private) specifically so this can be unit-tested directly
     * — Event::searchQuery()->execute() always returns empty under this
     * app's test SCOUT_DRIVER=null (see this file's own test's docblock),
     * so the only way to actually exercise this cap/cursor math in a test
     * is to call it directly with a plain Eloquent collection, bypassing ES
     * entirely. $events must be sorted oldest-first (ascending published_at
     * — see the query above), since "count >= cap" only means "there may be
     * more" when combined with that ordering.
     *
     * Backs off 1 second from the last included event's published_at when
     * at the cap, rather than using it exactly (caught in review): this
     * table's published_at column is whole-second precision, and
     * PublishEventsCommand stamps every event it publishes in one run with
     * the identical timestamp, so a large embargo release can genuinely tie
     * dozens of events on the same second. Landing the cursor exactly on
     * that second with a strict `gt()` next run would permanently exclude
     * any tied sibling that didn't make it into this capped batch. Backing
     * off means the next run's window re-opens that whole second — a rare
     * duplicate email for whatever was already sent from it is a far
     * safer failure mode than silently losing events forever, and a
     * backlog still drains within a few runs either way.
     */
    public function determineNewCursor(Collection $events, Carbon $cutoff): Carbon
    {
        return $events->count() >= self::MAX_EVENTS_PER_EMAIL
            ? Carbon::parse($events->last()->published_at)->subSecond()
            : $cutoff;
    }

    /**
     * Adapts a saved search's stored (already-normalized) criteria into the
     * shape EventSearchFilterBuilder expects — mainly resolving
     * remoteLocation from the stored slug to an id, since
     * NormalizeSavedSearchCriteriaAction stores that one field as a slug
     * (matching what the At Home search UI saves), unlike categories/tags,
     * which it already stores as integer ids.
     */
    private function criteriaFromSavedSearch(SavedSearch $savedSearch): array
    {
        $criteria = $savedSearch->criteria ?? [];

        return [
            'searchType' => $criteria['searchType'] ?? null,
            'lat' => $criteria['lat'] ?? null,
            'lng' => $criteria['lng'] ?? null,
            'live' => $criteria['live'] ?? false,
            'NElat' => $criteria['NElat'] ?? null,
            'NElng' => $criteria['NElng'] ?? null,
            'SWlat' => $criteria['SWlat'] ?? null,
            'SWlng' => $criteria['SWlng'] ?? null,
            'categoryIds' => $criteria['categories'] ?? [],
            'tagIds' => $criteria['tags'] ?? [],
            'remoteLocationId' => $this->resolveRemoteLocationId($criteria['remoteLocation'] ?? null),
            'priceMin' => $criteria['price'][0] ?? null,
            'priceMax' => $criteria['price'][1] ?? null,
            'start' => $criteria['start'] ?? null,
            'end' => $criteria['end'] ?? null,
        ];
    }

    private function resolveRemoteLocationId(?string $remoteLocation): ?int
    {
        if (! $remoteLocation) {
            return null;
        }

        if (is_numeric($remoteLocation)) {
            return (int) $remoteLocation;
        }

        return RemoteLocation::where('slug', $remoteLocation)->value('id');
    }
}
