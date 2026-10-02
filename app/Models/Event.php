<?php

namespace App\Models;

use App\Jobs\SyncEventSearchIndex;
use App\Models\Admin\CuratedEventCheck;
use App\Models\Admin\ReviewEvent;
use App\Models\Admin\StaffPick;
use App\Models\Admin\TrackClick;
use App\Models\Events\Advisory;
use App\Models\Events\AgeLimit;
use App\Models\Events\ContactLevel;
use App\Models\Events\ContentAdvisory;
use App\Models\Events\InteractiveLevel;
use App\Models\Events\Location;
use App\Models\Events\MobilityAdvisory;
use App\Models\Events\PriceRange;
use App\Models\Events\RemoteLocation;
use App\Models\Events\Show;
use App\Models\Events\ShowChangeLog;
use App\Models\Events\Ticket;
use App\Scopes\LatestPublishedFirstScope;
use App\Services\ImageHandler;
use App\Support\ShowHistory;
use App\Support\Slug;
use App\Traits\Favoritable;
use Carbon\Carbon;
use Elastic\ScoutDriverPlus\Searchable;
use Elastic\ScoutDriverPlus\Support\Query;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Event Model
 *
 * Represents an event in the system with relationships to users, organizers,
 * locations, and various other event-related models.
 */
class Event extends Model
{
    /**
     * Event statuses, named (see CLAUDE.md "Status Codes"). The wizard also
     * packs its step marker into status for drafts ('1' to '9', 'A' to 'D').
     */
    public const STATUS_DRAFT = 'd';

    public const STATUS_NEW = '0';

    public const STATUS_IN_REVIEW = 'r';

    public const STATUS_PUBLISHED = 'p';

    public const STATUS_EMBARGOED = 'e';

    public const STATUS_REJECTED = 'n';

    /** Approved: published, or embargoed until its date. */
    public const LIVE_STATUSES = [self::STATUS_PUBLISHED, self::STATUS_EMBARGOED];

    use Favoritable, HasFactory, SoftDeletes;
    use Searchable {
        queueMakeSearchable as protected scoutQueueMakeSearchable;
        queueRemoveFromSearch as protected scoutQueueRemoveFromSearch;
    }

    protected $casts = [
        'location_latlon' => 'array',
        'hasLocation' => 'boolean',
        'showtype_config' => 'array',
        'show_history' => 'array',
    ];

    protected $fillable = [
        'slug', 'user_id', 'timezone', 'category_id', 'attendance_type_id', 'interactive_level_id', 'organizer_id', 'description', 'name', 'largeImagePath', 'thumbImagePath', 'advisories_id', 'organizer_id', 'location_latlon', 'closingDate', 'websiteUrl', 'ticketUrl', 'show_times', 'price_range', 'status', 'tag_line', 'hasLocation', 'showtype', 'showtype_config', 'start_date', 'embargo_date', 'remote_description', 'published_at', 'call_to_action', 'age_limits_id', 'rank', 'archived',
    ];

    protected $appends = ['isFavorited', 'isShowing', 'isEditLocked'];

    protected $hidden = ['favorites', 'currentUserFavorite'];

    protected static function booted()
    {
        static::addGlobalScope(new LatestPublishedFirstScope);

        // A deleted event is gone from EI, so it gives up its slug: the name
        // it went by is free for the next listing, and its old URL finds
        // nothing (EventController::show answers 404).
        static::deleted(function (Event $event) {
            if (! $event->isForceDeleting()) {
                $event->releaseSlug();
            }
        });

        // Coming back, it takes a slug again the way it first got one: a
        // published event a collision-safe one from its name, anything else
        // the placeholder every unapproved event holds until approval.
        static::restoring(function (Event $event) {
            // restore() runs on a live row too (a repeated click, a stale
            // admin screen); a listing that was never deleted keeps its slug.
            if (! $event->trashed()) {
                return;
            }

            $event->slug = $event->published_at !== null
                ? static::finalSlug($event)
                : static::placeholderSlug();
        });

        // scopeStillRunning caches the timezones in use; a zone it has not
        // seen would be read as UTC until the cache expired.
        static::saved(function (Event $event) {
            $zones = Cache::get(self::TIMEZONES_IN_USE_CACHE);

            if ($event->timezone && is_array($zones) && ! in_array($event->timezone, $zones, true)) {
                Cache::forget(self::TIMEZONES_IN_USE_CACHE);
            }
        });
    }

    public const TIMEZONES_IN_USE_CACHE = 'events:timezones-in-use';

    /**
     * The slug a deleted event holds: unique by its id, and no name can ever
     * take it, because Str::slug() collapses runs of dashes and so never
     * produces the double dash.
     */
    public static function releasedSlug(int $id): string
    {
        return 'deleted--'.$id;
    }

    /** Swap this (already soft-deleted) event's slug for its released one. */
    public function releaseSlug(): void
    {
        $slug = static::releasedSlug($this->id);

        // Only while it's still deleted, so a restore that lands first keeps its slug.
        $released = static::withoutGlobalScopes()->whereKey($this->id)->whereNotNull('deleted_at')->update(['slug' => $slug]);

        if ($released) {
            $this->slug = $slug;
            $this->syncOriginalAttribute('slug');
        }
    }

    /** The random slug an event holds until approval gives it one from its name. */
    public static function placeholderSlug(): string
    {
        return Str::slug('new-event-'.Str::random(6));
    }

    public function shouldBeSearchable()
    {
        return $this->status === 'p' && ! $this->trashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Search-index sync
    |--------------------------------------------------------------------------
    | Every index write for an Event, whether Scout's observer or an explicit
    | syncSearchIndex(), becomes "reconcile event N": re-read the row and
    | index it if it is live and published, otherwise delete it from the
    | index. With scout.queue on that is a queued SyncEventSearchIndex job
    | that decides when it RUNS (tries 3, backoff 30s/120s), so a delayed
    | retry can never resurrect an event that was deleted or unpublished in
    | the meantime, and a slow or unreachable cluster never fails the
    | request. Inside deferringSearchSync() the calls are collected and
    | replayed once per event after the outermost transaction commits, so a
    | save that touches the event, its shows, tickets and genres costs one
    | job instead of up to ten.
    */

    protected static bool $deferringSearchSync = false;

    /** @var array<int, true> */
    protected static array $deferredSearchSyncIds = [];

    /** Scout hook: a single model's searchable() becomes a reconcile; a bulk collection (scout:import) keeps Scout's chunked job. */
    public function queueMakeSearchable($models)
    {
        if ($models->count() > 1) {
            return $this->scoutQueueMakeSearchable($models);
        }

        foreach ($models as $model) {
            static::queueSearchIndexSync($model->getKey());
        }
    }

    /** Scout hook: same as queueMakeSearchable, for unsearchable(). */
    public function queueRemoveFromSearch($models)
    {
        if ($models->count() > 1) {
            return $this->scoutQueueRemoveFromSearch($models);
        }

        foreach ($models as $model) {
            static::queueSearchIndexSync($model->getKey());
        }
    }

    public function syncSearchIndex(): void
    {
        if (static::$deferringSearchSync) {
            static::$deferredSearchSyncIds[$this->getKey()] = true;

            return;
        }

        static::queueSearchIndexSync($this->getKey());
    }

    /** Queue the reconcile (scout.queue on) or do it now (off: local dev, tests). */
    protected static function queueSearchIndexSync(int $id): void
    {
        if (! config('scout.queue')) {
            static::reconcileSearchIndex($id);

            return;
        }

        $model = new static;

        SyncEventSearchIndex::dispatch($id)
            ->onConnection($model->syncWithSearchUsing())
            ->onQueue($model->syncWithSearchUsingQueue());
    }

    /**
     * The one place the index is actually written for an event: read the
     * current row (no scopes, so a trashed row is seen and removed) and
     * index or delete accordingly. Called by SyncEventSearchIndex at run
     * time, or inline when scout.queue is off.
     */
    public static function reconcileSearchIndex(int $id): void
    {
        $fresh = static::withoutGlobalScopes()->find($id);

        if ($fresh && $fresh->shouldBeSearchable()) {
            $fresh->syncMakeSearchable($fresh->newCollection([$fresh]));

            return;
        }

        // A key-only stub is all the engine needs to delete the document.
        $stub = (new static)->setAttribute('id', $id);
        $stub->syncRemoveFromSearch($stub->newCollection([$stub]));
    }

    /**
     * Run $callback with Scout's observer silenced for Event and every
     * syncSearchIndex() call deferred; then reconcile each touched event
     * once, after commit. If the callback throws, the events touched so far
     * are still reconciled (their rows may have committed before the
     * failure; a reconcile only ever reflects what is in the database).
     * Nested calls join the outermost block.
     *
     * Enter this at transaction level 0 (as UpdateEventAction and
     * Show::normalizeToLocalNoon do). With scout.after_commit on, Scout runs
     * its observer at commit time, so if the caller is itself inside a
     * transaction the observer fires after this block has re-enabled syncing
     * and each save inside becomes its own (still correct, just not batched)
     * job.
     */
    public static function deferringSearchSync(callable $callback)
    {
        if (static::$deferringSearchSync) {
            return $callback();
        }

        static::$deferringSearchSync = true;
        static::$deferredSearchSyncIds = [];

        try {
            return static::withoutSyncingToSearch($callback);
        } finally {
            $ids = array_keys(static::$deferredSearchSyncIds);
            static::$deferringSearchSync = false;
            static::$deferredSearchSyncIds = [];

            if ($ids !== []) {
                // Runs immediately when no transaction is open; is dropped
                // with the transaction on rollback (nothing to reconcile then).
                DB::afterCommit(function () use ($ids) {
                    foreach ($ids as $id) {
                        static::queueSearchIndexSync($id);
                    }
                });
            }
        }
    }

    public function toSearchableArray()
    {
        // Get the location data
        $location = null;
        $hasValidLocation = false;

        if ($this->location &&
            $this->location->latitude &&
            $this->location->longitude &&
            $this->location->latitude != 0 &&
            $this->location->longitude != 0) {

            $location = [
                'lat' => (float) $this->location->latitude,
                'lon' => (float) $this->location->longitude,
            ];
            $hasValidLocation = true;
        }

        // Explicitly reload JUST the shows relationship (not the whole model
        // via refresh() — see searchableWith() below for why that would
        // undo the eager-loading it sets up) — a show added earlier in the
        // same request, after showsSelect was already lazy-loaded once
        // elsewhere, would otherwise still read back the stale cached
        // relation here (confirmed by git history: 2647957 fixed exactly
        // this staleness bug for shows specifically).
        //
        // KNOWN TRADEOFF: because this is an unconditional load() (not
        // loadMissing()), it fires once per model even during a batch
        // scout:import/MakeSearchableJob run, regardless of searchableWith()
        // already having batch-loaded the other 4 relations for the whole
        // chunk — so a full reindex still does 1 shows query per event
        // instead of 1 per chunk. Switching this to loadMissing() would
        // close that, but would also silently skip the reload in exactly
        // the single-model staleness scenario above, reopening 2647957 —
        // Scout's own searchableWith() hook has no way to signal "this call
        // is part of a trusted-fresh batch load" vs. "this instance may
        // already carry stale state," so there's no way to have both
        // guarantees at once without deeper changes to how Scout syncs.
        // Accepted as-is: still strictly fewer queries than the pre-existing
        // refresh()-per-model baseline (which reloaded the whole model, not
        // just this one relation), just not the full fix a batch reindex
        // could theoretically get.
        //
        // Only shows from two days ago on: search only ever asks about dates
        // from today forward (every search date picker's minimum is today;
        // the two days cover timezones; a hand-edited URL or an aged saved
        // search with a past start just matches fewer past shows), and each show is a nested object in
        // this document, which Elasticsearch refuses past 10,000. A long run
        // with years of history would otherwise become unindexable. Future
        // shows can't pass the cap: every one comes from a dateArray, whose
        // days from a year ago on EventUpdateRules limits to Show::MAX_ROWS
        // (older ones go to the show history, never rows), plus
        // at most a couple of days of just-past rows a save keeps. The
        // limit() is a guard set above that and under Elasticsearch's 10,000,
        // so it never trims a real upcoming date.
        $this->load(['showsSelect' => fn ($q) => $q
            ->where('date', '>=', now()->subDays(2)->startOfDay())
            ->reorder('date')
            ->limit(9900)]);
        $shows = $this->showsSelect;

        return [
            'name' => $this->name,
            'status' => $this->status,
            'showtype' => $this->showtype,
            'rank' => $this->rank,
            'category_id' => $this->category_id,
            'attendance_type_id' => $this->attendance_type_id,
            'location_latlon' => $location,  // Will be null if no valid coordinates
            'hasLocation' => $hasValidLocation,  // Only true if we have non-zero coordinates
            'shows' => $shows,
            'published_at' => $this->published_at ? Carbon::parse($this->published_at)->format('Y-m-d H:i:s') : null,
            'closingDate' => $this->closingDate ? Carbon::parse($this->closingDate)->format('Y-m-d H:i:s') : null,
            // The real end of the run, in UTC: search compares this against
            // "now" (stillRunningSearchFilter). closingDate alone is a wall
            // time with no zone, and comparing it as UTC dropped an LA run at
            // 5pm on its last day. ISO 8601 with a Z on purpose: a document
            // indexed before `elastic:migrate` adds the field gets it
            // dynamically mapped, and only this shape is detected as a date
            // ("Y-m-d H:i:s" became text, and the migration then failed).
            'closing_at' => $this->closingAt()?->format('Y-m-d\TH:i:s\Z'),
            'priceranges' => $this->pricerangesSelect,
            'genres' => $this->genreSelect,
            'remote_location_ids' => $this->remotelocations->pluck('id')->toArray(),
            'priority' => 5,
        ];
    }

    /**
     * Elastic Scout Driver Plus's own eager-loading hook (Searchable trait)
     * — DocumentFactory::makeFromModels() calls $models->withSearchableRelations()
     * on the whole batch before building documents, which loadMissing()s
     * whatever this returns, for BOTH a bulk scout:import and an ordinary
     * single-model save-triggered sync. Without this, every relation this
     * method reads (location, pricerangesSelect, genreSelect, remotelocations)
     * was a separate per-model lazy-load query — a real N+1 on a full
     * reindex. showsSelect is deliberately NOT here: it gets its own
     * explicit load() above on every call, not a loadMissing() that would
     * silently skip the refresh if some earlier code in the same request
     * already loaded it stale.
     */
    public function searchableWith()
    {
        return ['location', 'pricerangesSelect', 'genreSelect', 'remotelocations'];
    }

    /**
     * Everything a search-map marker and its popup need, and nothing else.
     * map.vue / map-mobile.vue read id, price_range and location_latlon for
     * the marker; map-element(-mobile).vue read slug, name, tag_line,
     * price_range and thumbImagePath for the popup.
     */
    public const MAP_PIN_FIELDS = ['id', 'slug', 'name', 'tag_line', 'price_range', 'thumbImagePath', 'location_latlon'];

    /**
     * The map's pins for these ids: only MAP_PIN_FIELDS, as plain arrays.
     * Shaped by hand rather than toArray() so the $appends accessors never
     * run — isFavorited is a query per row. An id the index still holds but
     * the table has lost is skipped.
     *
     * @param  int[]  $ids
     * @return array<int, array<string, mixed>>
     */
    public static function mapPins(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return static::query()
            ->whereIn('id', $ids)
            ->get(self::MAP_PIN_FIELDS)
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'slug' => $event->slug,
                'name' => $event->name,
                'tag_line' => $event->tag_line,
                'price_range' => $event->price_range,
                'thumbImagePath' => $event->thumbImagePath,
                'location_latlon' => $event->location_latlon,
            ])
            ->values()
            ->all();
    }

    /**
     * A stored UTC show datetime as its calendar day in this event's
     * timezone (see Show::localDay() for the rule), in $format.
     */
    public function localDate($utcDateTime, string $format = 'Y-m-d'): ?string
    {
        if (! $utcDateTime) {
            return null;
        }

        $timezone = Show::validTimezone($this->timezone);

        return Carbon::parse(Show::localDay($utcDateTime, $timezone, $this->usesCurtainTimes()).' 12:00:00', $timezone)->format($format);
    }

    /**
     * Whether this event's stored shows record real times of day — see
     * Show::usesCurtainTimes(). Reads the loaded `shows` relation, or the
     * `timed_shows_count` aggregate when a listing query supplied one
     * (FavoriteController) so the Hub never lazy-loads a schedule per card.
     */
    public function usesCurtainTimes(): bool
    {
        if (array_key_exists('timed_shows_count', $this->attributes)) {
            return (int) $this->timed_shows_count > 0;
        }

        // Memoised against the loaded collection: callers ask once per show
        // (get-event's show_days, the page's calendars), and a fresh walk of
        // a 2,000-show schedule each time would be quadratic.
        $shows = $this->shows;
        if ($this->curtainTimesMemo === null || $this->curtainTimesMemo[0] !== $shows) {
            $this->curtainTimesMemo = [$shows, Show::usesCurtainTimes($shows)];
        }

        return $this->curtainTimesMemo[1];
    }

    /** @var array{0: \Illuminate\Support\Collection, 1: bool}|null */
    private ?array $curtainTimesMemo = null;

    public function scopeUserEvents($query)
    {
        return $query->where('user_id', auth()->id());
    }

    /**
     * event_id has to be in the select alongside date — a hasMany relation
     * needs its own foreign key present in the result set to match rows
     * back to their parent during EAGER loading (with()/load()/loadMissing()
     * on a collection); a bare property access when nothing's preloaded
     * (the lazy-load path) doesn't need it, since there's only one parent
     * in scope and the WHERE clause alone already scopes correctly — which
     * is why this went unnoticed: nothing eager-loaded this relation before
     * searchableWith() below started doing so. Same reasoning applies to
     * pricerangesSelect(); genreSelect() (belongsToMany) doesn't have this
     * problem — Laravel always appends the pivot's own linking columns
     * regardless of an explicit select().
     */
    public function showsSelect()
    {
        return $this->hasMany(Show::class)->select('date', 'event_id');
    }

    public function genreSelect()
    {
        return $this->belongsToMany(Genre::class)->select('genre_id');
    }

    public function pricerangesSelect()
    {
        return $this->hasMany(PriceRange::class)->select('price', 'event_id');
    }

    /**
     * Helpful command to see published events
     *
     * @return bool
     */
    public function isPublished()
    {
        return $this->status == 'p';
    }

    /**
     * Determines which events are published for Laravel Scout
     *
     * @return bool
     */
    public function inProgress()
    {
        return $this->status != 'r' && $this->status != 'p' && $this->status != 'e' && $this->status != 'n';
    }

    /**
     * Determines which events are published
     *
     * @return bool
     */
    public function getIsPickedAttribute()
    {
        return $this->status == 'p';
    }

    /**
     * Determines if the show is still available
     *
     * @return bool
     */
    public function getIsShowingAttribute()
    {
        return $this->closingAt()?->gte(Carbon::now()) ?? false;
    }

    /**
     * When the run really ends, as a UTC instant. closingDate is a wall time
     * in the event's own timezone (the end of its last local day, see
     * Show::calculateLastDate), so it only means a moment once read in that
     * zone.
     */
    public function closingAt(): ?Carbon
    {
        if (! $this->closingDate) {
            return null;
        }

        $wallTime = Carbon::parse($this->closingDate)->format('Y-m-d H:i:s');

        return Carbon::parse($wallTime, Show::validTimezone($this->timezone))->utc();
    }

    /**
     * Events whose run has not ended yet, judged in each event's own
     * timezone: closingDate is compared with what the clock says right now
     * where the event is. One SQL CASE over the timezones events use.
     */
    public function scopeStillRunning($query, ?Carbon $at = null)
    {
        $at = ($at ?? Carbon::now())->copy()->utc();

        // The zones in use (a few dozen); a zone added since the list was
        // cached falls back to UTC, the old rule, until it refreshes.
        $zones = Cache::remember(self::TIMEZONES_IN_USE_CACHE, 3600, fn () => static::withoutGlobalScopes()
            ->whereNotNull('timezone')->distinct()->pluck('timezone')->all());

        $table = $this->getTable();
        if ($zones === []) {
            return $query->where("{$table}.closingDate", '>=', $at->format('Y-m-d H:i:s'));
        }

        $case = "CASE `{$table}`.`timezone`";
        $bindings = [];
        foreach ($zones as $zone) {
            $case .= ' WHEN ? THEN ?';
            $bindings[] = $zone;
            $bindings[] = $at->copy()->setTimezone(Show::validTimezone($zone))->format('Y-m-d H:i:s');
        }
        $case .= ' ELSE ? END';
        $bindings[] = $at->format('Y-m-d H:i:s');

        return $query->whereRaw("`{$table}`.`closingDate` >= ({$case})", $bindings);
    }

    /**
     * Only published events, whatever the index holds. The index should only
     * ever contain them (shouldBeSearchable), but a stale document survived an
     * unpublish once (event 5692, a draft listed in search for weeks), and
     * search trusted the index outright. Organizer documents carry no status,
     * so they pass, for the queries that join the two indices.
     */
    public static function publishedSearchFilter()
    {
        return Query::bool()
            ->should(Query::term()->field('status')->value(self::STATUS_PUBLISHED))
            ->should(Query::bool()->mustNot(Query::exists()->field('status')))
            ->minimumShouldMatch(1);
    }

    /**
     * The search-side twin of scopeStillRunning, on the closing_at instant
     * in the index. A document indexed before closing_at existed falls back
     * to the old closingDate rule until it is reindexed.
     */
    public static function stillRunningSearchFilter()
    {
        return Query::bool()
            ->should(Query::range()->field('closing_at')->gte('now'))
            ->should(Query::bool()
                ->mustNot(Query::exists()->field('closing_at'))
                ->filter(Query::range()->field('closingDate')->gte('now/d')))
            ->minimumShouldMatch(1);
    }

    /**
     * How long after its run ends an event stays editable by its organizers.
     * Past this a published event is a historical record: only moderators
     * and admins can change it, and an organizer running the show again
     * duplicates it into a new listing instead (duplicate() below). Inside
     * the window a finished run is still editable, so an organizer can add
     * dates to a just-ended event and bring it back at its own URL.
     */
    public const EDIT_WINDOW_DAYS = 90;

    /**
     * What an organizer is told wherever the lock is hit on the website. The
     * dashboard's modal (Creation/index.vue) carries the same words.
     */
    public const EDIT_LOCKED_MESSAGE = 'Trying to edit an event that is over 90 days old? If you wish to add new dates to your event, please duplicate the existing one and create a new listing, e.g. "Event Name (2026)" or "Event Name (Fall 2026)". This helps us preserve the historical record of past events. Need help? Contact us at: support@everythingimmersive.com';

    /**
     * Whether the event has left its edit window: published (or embargoed,
     * which is approved content) and closed more than EDIT_WINDOW_DAYS ago.
     *
     * A draft never qualifies, whatever its dates: it was never on the site,
     * so there is no record to preserve, and locking it would strand an
     * abandoned draft that could then be neither finished nor deleted. A
     * null closingDate means no schedule yet, not "long ago".
     */
    public function isHistorical(): bool
    {
        if (! in_array($this->status, self::LIVE_STATUSES, true) || $this->closingDate === null) {
            return false;
        }

        return $this->closingAt()->lt(Carbon::now()->subDays(self::EDIT_WINDOW_DAYS));
    }

    /**
     * Whether the ticket button opens an email (mailto:) instead of a web
     * page, for shows with no website that sell tickets by email only.
     */
    public function ticketsByEmail(): bool
    {
        return is_string($this->ticketUrl) && str_starts_with(strtolower($this->ticketUrl), 'mailto:');
    }

    /**
     * Whether $user is refused changes to this event because it is
     * historical. Moderators and admins keep it for corrections and
     * backfills. Every write path — the hosting controller and the MCP
     * tools — asks this one question, so the rule cannot drift between them.
     */
    public function isEditLockedFor(?User $user): bool
    {
        return $this->isHistorical() && ! $user?->isModerator();
    }

    public const IN_REVIEW_MESSAGE = 'This event is under review and cannot be edited until an admin approves or rejects it.';

    public const RENAME_NEEDS_REVIEW_MESSAGE = 'A live event is renamed through review: request the new name (on the website, the Name step offers this) and an admin will approve it.';

    /**
     * Submitted and waiting for an admin: frozen for its organizer, so what
     * gets approved is what was submitted. Moderators can still edit. Every
     * write path (hosting controller, MCP tools) asks this, as with
     * isEditLockedFor.
     */
    public function isInReviewFor(?User $user): bool
    {
        return $this->status === 'r' && ! $user?->isModerator();
    }

    /**
     * A published or embargoed event keeps its name until a name-change
     * request is approved (NameChangeRequestService). Moderators rename
     * directly.
     */
    public function renameNeedsReviewFor(?User $user, ?string $newName): bool
    {
        // Spacing alone is not a rename: input is trimmed on the way in, and
        // some stored names carry double spaces or line breaks.
        $normalize = fn (?string $name) => preg_replace('/\s+/u', ' ', trim((string) $name));

        return $newName !== null
            && $normalize($newName) !== $normalize($this->name)
            && in_array($this->status, self::LIVE_STATUSES, true)
            && ! $user?->isModerator();
    }

    /**
     * The moment an embargoed event goes live, as a real instant.
     *
     * embargo_date is deliberately NOT cast: it is a wall-clock time in the
     * event's own timezone, "Y-m-d H:i:s" (the wizard stores noon on the day
     * the organizer picked, so "publish on the 20th" means the 20th where the
     * event is, not the 20th in London). Every reader that compares it with
     * "now" must go through here so the publish cron, admin approval and
     * validation cannot disagree about what the stored value means. A blank
     * or junk timezone reads as UTC (Show::validTimezone), as the cron always
     * did for a blank one.
     */
    public function embargoLiftsAt(): ?Carbon
    {
        if (! $this->embargo_date) {
            return null;
        }

        return Carbon::parse((string) $this->embargo_date, Show::validTimezone($this->timezone));
    }

    /**
     * Whether the embargo is still holding the event back at $now.
     */
    public function embargoIsPending(?Carbon $now = null): bool
    {
        $liftsAt = $this->embargoLiftsAt();

        return $liftsAt !== null && $liftsAt->gt($now ?? Carbon::now());
    }

    /**
     * The same answer for the signed-in viewer, appended to the JSON so the
     * dashboard can show the lock modal instead of sending a request that
     * would be refused. Runs no query: closingDate is a column and the user
     * is already loaded, so it is safe on bulk serialization.
     */
    public function getIsEditLockedAttribute(): bool
    {
        return $this->isEditLockedFor(auth()->user());
    }

    /**
     * Each event belongs to One User
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all users who can manage this event through the organizer
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function owners()
    {
        return $this->organizer->allUsers();
    }

    /**
     * Each event has a conversation
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the attendance type for this event
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function attendanceType()
    {
        return $this->belongsTo(AttendanceType::class);
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * Get all videos related to this event
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function videos()
    {
        return $this->morphMany(Video::class, 'videoable');
    }

    /**
     * Each event hasOne StaffPick
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function staffpick()
    {
        return $this->hasOne(StaffPick::class);
    }

    /**
     * Each event hasOne curanted check
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function curatedCheck()
    {
        return $this->hasOne(CuratedEventCheck::class);
    }

    /**
     * Each event has many event reviews
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function eventreviews()
    {
        return $this->hasMany(ReviewEvent::class)
            ->orderBy('rank', 'ASC');
    }

    /**
     * Each event has many clicks
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function clicks()
    {
        return $this->hasMany(TrackClick::class);
    }

    /**
     * Each event belongs to One Organizer
     *
     * @return \Illuminate\Database\Eloquent\Relations/belongsTo
     */
    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    /**
     * Each Event has One Location
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function location()
    {
        return $this->hasOne(Location::class);
    }

    /**
     * Each Event has one Expectation Model
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function advisories()
    {
        return $this->hasOne(Advisory::class);
    }

    /**
     * Each event has many shows
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function shows()
    {
        return $this->hasMany(Show::class)->orderBy('date', 'DESC');
    }

    /**
     * The event's ticket tiers, stored once per event.
     *
     * Tiers used to live on each show as an identical copy per date;
     * ei:backfill-event-tickets moved them here. Nothing reads the leftover
     * show copies; they go when their show does or the tickets are next saved.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function tickets()
    {
        // By name: the show copies were always read back through the
        // (ticket_type, ticket_id, name) index, so hosts and visitors have
        // always seen tiers alphabetically.
        return $this->morphMany(Ticket::class, 'ticket')->orderBy('name')->orderBy('id');
    }

    /** The event's tiers. */
    public function currentTickets(): \Illuminate\Support\Collection
    {
        return $this->tickets;
    }

    /**
     * Each event has many eventrequest
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function eventRequest()
    {
        return $this->hasMany(EventRequest::class);
    }

    /**
     * Each event has many price ranges
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function priceranges()
    {
        return $this->hasMany(PriceRange::class);
    }

    /**
     * Each event can belong to many shows
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
     */
    public function genres()
    {
        return $this->belongsToMany(Genre::class);
    }

    /**
     * Each event can belong to many shows
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
     */
    public function age_limits()
    {
        return $this->belongsTo(AgeLimit::class);
    }

    /**
     * Each event can belong to one interactive level
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function interactive_level()
    {
        return $this->belongsTo(InteractiveLevel::class);
    }

    /**
     * Each event can belong to many remote types
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
     */
    public function remotelocations()
    {
        return $this->belongsToMany(RemoteLocation::class);
    }

    /**
     * Each event can belong to many ContactLevels
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
     */
    public function contactlevels()
    {
        return $this->belongsToMany(ContactLevel::class);
    }

    /**
     * Each event can belong to many ContactLevels
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
     */
    public function contentadvisories()
    {
        return $this->belongsToMany(ContentAdvisory::class);
    }

    /**
     * Each event can belong to many ContactLevels
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
     */
    public function mobilityadvisories()
    {
        return $this->belongsToMany(MobilityAdvisory::class);
    }

    /**
     * Sets the Route Key to slug instead of ID
     *
     * @return Route Key Name
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Create a new event for the given organizer
     *
     * @param  int  $organizerId
     * @return \App\Models\Event
     */
    public static function newEvent($organizerId)
    {
        $event = self::create([
            'user_id' => auth()->id(),
            'slug' => static::placeholderSlug(),
            'organizer_id' => $organizerId,
            'status' => '0',
        ]);
        $event->location()->create([]);
        $event->advisories()->create([]);

        return $event;
    }

    /**
     * Finds all the current live events
     *
     * @return a collection of the live events with priceranges attached
     */
    public static function getMostExpensive()
    {
        return Event::where('status', 'p')
            ->with('priceranges')
            ->whereDate('closingDate', '>=', date('Y-m-d'))
            ->get()
            ->map(function ($event) {
                return $event->priceranges->pluck('price');
            })
            ->flatten()
            ->max();
    }

    /**
     * Check if an event with the same name already exists
     *
     * @param  Event  $event
     * @param  Request  $request
     * @return bool
     */
    public function exists($event, $request)
    {
        return Event::where('slug', Str::slug($request->name))
            ->where('id', '!=', $event->id)
            ->exists();
    }

    /**
     * Whether a listing on EI (published or embargoed, not deleted) other than
     * $exceptId already goes by this name. Names match the way their URLs
     * would, so case and punctuation don't make a different name; one that
     * slugs to nothing (all CJK / emoji) is compared as written instead.
     */
    public static function nameTakenOnSite(string $name, ?int $exceptId = null): bool
    {
        $key = function (?string $n): string {
            $slug = Str::slug((string) $n);

            return $slug !== '' ? $slug : mb_strtolower(trim((string) $n));
        };
        $wanted = $key($name);

        if ($wanted === '') {
            return false;
        }

        return static::withoutGlobalScope(LatestPublishedFirstScope::class)
            ->whereIn('status', self::LIVE_STATUSES)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->pluck('name')
            ->contains(fn ($other) => $key($other) === $wanted);
    }

    /**
     * Generate a unique slug for the event
     */
    public static function finalSlug(Event $event): string
    {
        // Slug::base() guarantees a non-empty base even for CJK / emoji /
        // symbol-only names, which Str::slug() alone reduces to ''.
        $baseSlug = Slug::base($event->name, 'event');

        // If the base slug is available, use it
        if (! static::slugExists($baseSlug, $event->id)) {
            return $baseSlug;
        }

        // Try with city if available (e.g., "event-name-london")
        if ($event->location?->city) {
            $citySlug = $baseSlug.'-'.Str::slug($event->location->city);
            if (! static::slugExists($citySlug, $event->id)) {
                return $citySlug;
            }
        }

        // Try with organizer name (e.g., "event-name-organizername")
        if ($event->organizer?->name) {
            $organizerSlug = $baseSlug.'-'.Str::slug($event->organizer->name);
            if (! static::slugExists($organizerSlug, $event->id)) {
                return $organizerSlug;
            }
        }

        // If still not unique, add short incremental number
        $count = 2; // Start at 2 since it's more natural in URLs
        do {
            $newSlug = $baseSlug.'-'.$count;
            $count++;
        } while (static::slugExists($newSlug, $event->id));

        return $newSlug;
    }

    /**
     * Check if a slug exists for any other event
     */
    private static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        return static::withTrashed()
            ->where('slug', $slug)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->exists();
    }

    /**
     * How many unpublished events (drafts, in-review, rejected) one organizer
     * may hold at once. Admins are exempt everywhere this is enforced: the web
     * create/duplicate endpoints, the MCP create-event-draft tool, and the
     * client-side pre-check in resources/js/PageComponents/Creation/index.vue
     * (which must be kept in step with this value).
     */
    public const MAX_UNPUBLISHED_EVENTS = 10;

    public static function countUnpublishedEvents($organizerId)
    {
        return self::where('organizer_id', $organizerId)
            ->whereNotIn('status', self::LIVE_STATUSES) // Not published or embargoed
            ->count();
    }

    /**
     * Bulk form of countUnpublishedEvents for a caller building counts for
     * several organizers at once (e.g. whoami listing a user's teams) — one
     * grouped query instead of one count query per organizer (EI-LARAVEL-W).
     * Organizers with zero unpublished events are simply absent from the map.
     *
     * @param  array<int>  $organizerIds
     * @return \Illuminate\Support\Collection<int, int> organizer_id => count
     */
    public static function countUnpublishedEventsForOrganizers(array $organizerIds)
    {
        // LatestPublishedFirstScope's global orderBy('published_at') isn't in the GROUP BY
        // below, which MySQL's ONLY_FULL_GROUP_BY mode rejects. Irrelevant to a
        // count anyway, so drop it rather than add it to the grouping.
        return self::withoutGlobalScope(LatestPublishedFirstScope::class)
            ->whereIn('organizer_id', $organizerIds)
            ->whereNotIn('status', self::LIVE_STATUSES)
            ->selectRaw('organizer_id, count(*) as aggregate')
            ->groupBy('organizer_id')
            ->pluck('aggregate', 'organizer_id');
    }

    public function nameChangeRequests()
    {
        return $this->morphMany(NameChangeRequest::class, 'requestable');
    }

    public function showChangeLogs()
    {
        return $this->hasMany(ShowChangeLog::class)->orderBy('created_at', 'desc');
    }

    /**
     * Create a duplicate of the event
     *
     * @return \App\Models\Event
     */
    public function duplicate()
    {
        return DB::transaction(function () {
            // Create new event with duplicated attributes (excluding location, ticket, and price data)
            $newEvent = $this->replicate(['location_latlon', 'ticketUrl', 'price_range', 'closingDate', 'show_times', 'showtype', 'show_history']);
            $newEvent->slug = static::placeholderSlug();
            // The copy is credited to whoever made it (the admin "Submitted by"
            // column), not to the author of the event it was copied from.
            $newEvent->user_id = auth()->id() ?? $this->user_id;
            $newEvent->status = '0'; // Set as draft
            $newEvent->name = $this->name.' (Copy)';
            $newEvent->published_at = null;
            $newEvent->hasLocation = $this->attendance_type_id === 1; // Set hasLocation based on attendance type (true for in-person, false for remote)
            $newEvent->attendance_type_id = $this->attendance_type_id; // Copy the attendance type (in-person vs remote)
            $newEvent->save();

            // Create empty location record (required for all events) - duplicated location data commented out per client request
            // if ($this->location) {
            //     $newLocation = $this->location->replicate();
            //     $newLocation->event_id = $newEvent->id;
            //     $newLocation->save();
            // }
            // Create empty location record instead
            $newEvent->location()->create([]);

            // Duplicate advisories
            if ($this->advisories) {
                $newAdvisories = $this->advisories->replicate();
                $newAdvisories->event_id = $newEvent->id;
                $newAdvisories->save();
            }

            // Sync relationships
            $newEvent->genres()->sync($this->genres->pluck('id'));
            $newEvent->contentadvisories()->sync($this->contentadvisories->pluck('id'));
            $newEvent->mobilityadvisories()->sync($this->mobilityadvisories->pluck('id'));
            $newEvent->contactlevels()->sync($this->contactlevels->pluck('id'));
            $newEvent->remotelocations()->sync($this->remotelocations->pluck('id'));

            // Price ranges duplication commented out per client request
            // foreach ($this->priceranges as $priceRange) {
            //     $newPriceRange = $priceRange->replicate();
            //     $newPriceRange->event_id = $newEvent->id;
            //     $newPriceRange->save();
            // }

            // Shows/dates duplication commented out per client request
            // foreach ($this->shows as $show) {
            //     $newShow = $show->replicate();
            //     $newShow->event_id = $newEvent->id;
            //     $newShow->save();

            //     // Duplicate tickets for this show
            //     foreach ($show->tickets as $ticket) {
            //         $newTicket = $ticket->replicate();
            //         $newTicket->ticket_type = get_class($newShow);
            //         $newTicket->ticket_id = $newShow->id;
            //         $newTicket->save();
            //     }
            // }

            // Duplicate images - copy actual files to new location
            // Note: File copy operations are not rolled back if transaction fails,
            // but image database records will be rolled back, preventing orphaned references
            ImageHandler::duplicateImages($this, $newEvent, 'event');

            // Duplicate videos
            foreach ($this->videos as $video) {
                $newVideo = $video->replicate();
                $newVideo->videoable_id = $newEvent->id;
                $newVideo->save();
            }

            return $newEvent->fresh([
                'location',
                'advisories',
                'genres',
                'contentadvisories',
                'mobilityadvisories',
                'contactlevels',
                'remotelocations',
                // 'priceranges', // Commented out since we're not duplicating price ranges
                // 'shows.tickets', // Commented out since we're not duplicating shows/tickets
                'images',
                'videos',
            ]);
        });
    }

    /**
     * How much longer this event is bookable, in words. Reads the aggregate
     * `remaining_shows_count`/`next_show_date` columns a caller must select onto
     * the model via withCount()/withMin() (see FavoriteController::index) — this
     * is deliberately NOT an $appends accessor that queries per-row, to avoid the
     * N+1 pattern already logged in project memory for this exact model.
     *
     * 'showtype' a (always available) and l (limited) each resolve to a single
     * sentinel Show row rather than real discrete dates (see Show::targetDatesFor),
     * so a raw count/date for those would misleadingly read as "1 date left".
     */
    public function remainingSummary(): array
    {
        if (in_array($this->showtype, ['a', 'l'], true)) {
            return [
                'type' => 'ongoing',
                'label' => $this->showtype === 'a' ? 'Always available' : 'Ongoing',
            ];
        }

        $count = (int) ($this->remaining_shows_count ?? 0);

        if ($count === 0) {
            return ['type' => 'ended', 'label' => 'Run has ended', 'count' => 0];
        }

        // Show.date is stored as a true UTC instant (see Show::targetDatesFor), so
        // the future/past comparison itself is timezone-agnostic — only display
        // needs converting back to the event's own local timezone.
        $nextDate = $this->next_show_date
            ? $this->showDayInLocalTime($this->next_show_date)->format('M j')
            : null;

        $label = match (true) {
            $count === 1 && $nextDate !== null => "Last date: {$nextDate}",
            $count === 1 => '1 date left',
            $nextDate !== null => "{$count} dates left, next {$nextDate}",
            default => "{$count} dates left",
        };

        return ['type' => 'dated', 'label' => $label, 'count' => $count];
    }

    /**
     * The event's overall run — first show through last show — as a display
     * string, for a "run dates" line (e.g. the Liked Events pages) rather than
     * remainingSummary()'s forward-looking "N dates left" framing. Reads the
     * aggregate `first_show_date`/`last_show_date` columns a caller selects via
     * withMin()/withMax() (same N+1-avoidance convention as remainingSummary()
     * — see that method's doc comment). Returns null for showtype 'a'/'l',
     * which have no real discrete range; callers should fall back to
     * remainingSummary()'s label for those.
     */
    public function dateRangeLabel(): ?string
    {
        if (in_array($this->showtype, ['a', 'l'], true)) {
            return null;
        }

        if (! $this->first_show_date) {
            return null;
        }

        $first = $this->firstRunDay();
        $last = $this->last_show_date ? $this->showDayInLocalTime($this->last_show_date) : $first;

        if ($first->isSameDay($last)) {
            return $first->format('M j, Y');
        }

        return $first->format('M j, Y').' - '.$last->format('M j, Y');
    }

    /**
     * A stored show datetime as noon of its day in this event's timezone,
     * for the Hub's aggregate first/next/last dates.
     */
    private function showDayInLocalTime($utcDateTime): Carbon
    {
        $tz = Show::validTimezone($this->timezone);

        return Carbon::parse(Show::localDay($utcDateTime, $tz, $this->usesCurtainTimes()).' 12:00:00', $tz);
    }

    /**
     * The run's first day, at noon in this event's timezone: the oldest day
     * in the compact show history when it holds one (days more than a year
     * old are not rows), otherwise the first_show_date aggregate.
     */
    private function firstRunDay(): Carbon
    {
        $tz = Show::validTimezone($this->timezone);
        $rowsFirst = $this->showDayInLocalTime($this->first_show_date);
        $historyFirst = ShowHistory::firstDay($this->show_history);

        if ($historyFirst !== null && $historyFirst < $rowsFirst->toDateString()) {
            return Carbon::parse($historyFirst.' 12:00:00', $tz);
        }

        return $rowsFirst;
    }

    /**
     * First/last run dates broken into day-abbreviation + day-number pieces
     * (e.g. ['day' => 'Sat', 'date' => 21]) for the Liked Events detail page's
     * check-in/checkout-style timeline. Same timezone handling as
     * dateRangeLabel() — the event's own timezone, not the viewer's — so the
     * day-of-week shown can't drift from what dateRangeLabel() already prints.
     */
    public function runDateParts(): ?array
    {
        if (in_array($this->showtype, ['a', 'l'], true) || ! $this->first_show_date) {
            return null;
        }

        $first = $this->firstRunDay();
        $last = $this->last_show_date ? $this->showDayInLocalTime($this->last_show_date) : $first;

        return [
            'first' => ['day' => $first->format('D'), 'date' => $first->day, 'label' => $first->format('M j, Y')],
            'last' => ['day' => $last->format('D'), 'date' => $last->day, 'label' => $last->format('M j, Y')],
        ];
    }

    public function getFirstShowTicketsAttribute()
    {
        // No dates, no offer: the event set outlives a deleted schedule, but
        // the page (and its JSON-LD) never listed tiers for an event with no
        // shows.
        $hasShows = $this->relationLoaded('shows') ? $this->shows->isNotEmpty() : $this->shows()->exists();
        if (! $hasShows) {
            return collect();
        }

        // The event's own tier set. The name dates from when tiers lived on
        // each show.
        return $this->tickets;
    }
}
