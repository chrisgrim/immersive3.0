<?php

namespace App\Models;

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
use App\Scopes\PublishedScope;
use App\Services\ImageHandler;
use App\Support\Slug;
use App\Traits\Favoritable;
use Carbon\Carbon;
use Elastic\ScoutDriverPlus\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
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
    use Favoritable, HasFactory, Searchable, SoftDeletes;

    protected $casts = [
        'location_latlon' => 'array',
        'hasLocation' => 'boolean',
        'showtype_config' => 'array',
    ];

    protected $fillable = [
        'slug', 'user_id', 'timezone', 'category_id', 'attendance_type_id', 'interactive_level_id', 'organizer_id', 'description', 'name', 'largeImagePath', 'thumbImagePath', 'advisories_id', 'organizer_id', 'location_latlon', 'closingDate', 'websiteUrl', 'ticketUrl', 'show_times', 'price_range', 'status', 'tag_line', 'hasLocation', 'showtype', 'showtype_config', 'start_date', 'embargo_date', 'remote_description', 'published_at', 'call_to_action', 'age_limits_id', 'rank', 'archived',
    ];

    protected $appends = ['isFavorited', 'isShowing'];

    protected $hidden = ['favorites', 'currentUserFavorite'];

    protected static function booted()
    {
        static::addGlobalScope(new PublishedScope);
    }

    public function shouldBeSearchable()
    {
        return $this->status === 'p';
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
        $this->load('showsSelect');
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
        return $this->closingDate >= Carbon::now();
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
            'slug' => Str::slug('new-event-'.Str::random(6)),
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
        } while (static::slugExists($newSlug, $event->id) && $count < 100);

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
            ->whereNotIn('status', ['p', 'e']) // Not published or embargoed
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
        // PublishedScope's global orderBy('published_at') isn't in the GROUP BY
        // below, which MySQL's ONLY_FULL_GROUP_BY mode rejects. Irrelevant to a
        // count anyway, so drop it rather than add it to the grouping.
        return self::withoutGlobalScope(PublishedScope::class)
            ->whereIn('organizer_id', $organizerIds)
            ->whereNotIn('status', ['p', 'e'])
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
            $newEvent = $this->replicate(['location_latlon', 'ticketUrl', 'price_range', 'closingDate', 'show_times', 'showtype']);
            $newEvent->slug = Str::slug('new-event-'.Str::random(6));
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
     * Get tickets from just the first show as an accessor
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
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
            ? Carbon::parse($this->next_show_date, 'UTC')->setTimezone($this->timezone ?? 'Etc/UTC')->format('M j')
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

        $tz = $this->timezone ?? 'Etc/UTC';
        $first = Carbon::parse($this->first_show_date, 'UTC')->setTimezone($tz);
        $last = $this->last_show_date
            ? Carbon::parse($this->last_show_date, 'UTC')->setTimezone($tz)
            : $first;

        if ($first->isSameDay($last)) {
            return $first->format('M j, Y');
        }

        return $first->format('M j, Y').' - '.$last->format('M j, Y');
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

        $tz = $this->timezone ?? 'Etc/UTC';
        $first = Carbon::parse($this->first_show_date, 'UTC')->setTimezone($tz);
        $last = $this->last_show_date
            ? Carbon::parse($this->last_show_date, 'UTC')->setTimezone($tz)
            : $first;

        return [
            'first' => ['day' => $first->format('D'), 'date' => $first->day, 'label' => $first->format('M j, Y')],
            'last' => ['day' => $last->format('D'), 'date' => $last->day, 'label' => $last->format('M j, Y')],
        ];
    }

    public function getFirstShowTicketsAttribute()
    {
        // First check if shows are already loaded to avoid additional query
        if ($this->relationLoaded('shows')) {
            // shows() orders date DESC, so sort the loaded collection ascending to get
            // the genuinely earliest-dated ("first") show rather than the last.
            $firstShow = $this->shows->sortBy('date')->first();

            // If the first show exists and tickets are loaded
            if ($firstShow && $firstShow->relationLoaded('tickets')) {
                return $firstShow->tickets;
            }

            // If the first show exists but tickets aren't loaded
            if ($firstShow) {
                return $firstShow->tickets()->get();
            }
        }

        // Fall back to query if shows aren't loaded. reorder() clears the shows() relation's
        // DESC ordering so the earliest-dated show is selected.
        $firstShow = $this->shows()->reorder('date', 'asc')->with('tickets')->first();

        return $firstShow ? $firstShow->tickets : collect();
    }
}
