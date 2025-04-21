<?php

namespace App\Models;

use App\Models\{Conversation, Genre, Organizer, User, Category};
use App\Models\Events\{ Show, PriceRange, Advisory, Location, AgeLimit, InteractiveLevel, RemoteLocation, ContactLevel, ContentAdvisory, MobilityAdvisory};
use App\Models\Admin\{ ReviewEvent, StaffPick, TrackClick, CuratedEventCheck };
use App\Scopes\PublishedScope;
use App\Traits\{Favoritable};
use Elastic\ScoutDriverPlus\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\NameChangeRequest;

/**
 * Event Model
 * 
 * Represents an event in the system with relationships to users, organizers,
 * locations, and various other event-related models.
 */
class Event extends Model
{
    use Favoritable, SoftDeletes, Searchable;

    protected $casts = [
        'location_latlon' => 'array',
        'hasLocation' => 'boolean',
    ];

    protected $fillable = [
        'slug', 'user_id', 'timezone', 'category_id','interactive_level_id','organizer_id','description','name','largeImagePath','thumbImagePath','advisories_id', 'organizer_id', 'location_latlon', 'closingDate','websiteUrl','ticketUrl','show_times','price_range', 'status','tag_line', 'hasLocation', 'showtype', 'embargo_date', 'remote_description', 'published_at', 'call_to_action', 'age_limits_id', 'rank', 'archived'
    ];

    protected $appends = ['isFavorited', 'isShowing'];


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
                'lat' => (float)$this->location->latitude,
                'lon' => (float)$this->location->longitude
            ];
            $hasValidLocation = true;
        }

        return [
            'name' => $this->name,
            'status' => $this->status,
            'showtype' => $this->showtype,
            'rank' => $this->rank,
            'category_id' => $this->category_id,
            'location_latlon' => $location,  // Will be null if no valid coordinates
            'hasLocation' => $hasValidLocation,  // Only true if we have non-zero coordinates
            'shows' => $this->showsSelect,
            'published_at' => $this->published_at ? Carbon::parse($this->published_at)->format('Y-m-d H:i:s') : null,
            'closingDate' => $this->closingDate ? Carbon::parse($this->closingDate)->format('Y-m-d H:i:s') : null,
            'priceranges' => $this->pricerangesSelect,
            'genres' => $this->genreSelect,
            'priority' => 5,
        ];
    }

    public function scopeUserEvents($query)
    {
        return $query->where('user_id', auth()->id());
    } 

    public function showsSelect()
    {
        return $this->hasMany(Show::class)->select('date');
    }

    public function genreSelect()
    {
        return $this->belongsToMany(Genre::class)->select('genre_id');
    }

    public function pricerangesSelect()
    {
        return $this->hasMany(PriceRange::class)->select('price');
    }

    /**
    * Helpful command to see published events
    *
    * @return bool
    */
    public function isPublished() {
        return $this->status == 'p';
    }

    /**
    * Determines which events are published for Laravel Scout
    *
    * @return bool
    */
    public function inProgress() {
        return $this->status != 'r' && $this->status != 'p' && $this->status != 'e' && $this->status != 'n';
    }

    /**
    * Determines which events are published
    *
    * @return bool
    */
    public function getIsPickedAttribute() {
        return $this->status == 'p';
    }

    /**
    * Determines if the show is still available
    *
    * @return boolean
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
     * @param int $organizerId
     * @return \App\Models\Event
     */
    public static function newEvent($organizerId)
    {
        $event = self::create([
            'user_id' => auth()->id(),
            'slug' => Str::slug('new-event-' . Str::random(6)),
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
            ->whereDate('closingDate', '>=', date("Y-m-d"))
            ->get()
            ->map(function($event) { return $event->priceranges->pluck('price');})
            ->flatten()
            ->max();
    }

    /**
     * Check if an event with the same name already exists
     *
     * @param Event $event
     * @param Request $request
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
     *
     * @param Event $event
     * @return string
     */
    public static function finalSlug(Event $event): string 
    {
        $baseSlug = Str::slug($event->name);
        
        // If the base slug is available, use it
        if (!static::slugExists($baseSlug, $event->id)) {
            return $baseSlug;
        }

        // Try with city if available (e.g., "event-name-london")
        if ($event->location?->city) {
            $citySlug = $baseSlug . '-' . Str::slug($event->location->city);
            if (!static::slugExists($citySlug, $event->id)) {
                return $citySlug;
            }
        }

        // Try with organizer name (e.g., "event-name-organizername")
        if ($event->organizer?->name) {
            $organizerSlug = $baseSlug . '-' . Str::slug($event->organizer->name);
            if (!static::slugExists($organizerSlug, $event->id)) {
                return $organizerSlug;
            }
        }

        // If still not unique, add short incremental number
        $count = 2; // Start at 2 since it's more natural in URLs
        do {
            $newSlug = $baseSlug . '-' . $count;
            $count++;
        } while (static::slugExists($newSlug, $event->id) && $count < 100);

        return $newSlug;
    }

    /**
     * Check if a slug exists for any other event
     *
     * @param string $slug
     * @param int|null $excludeId
     * @return bool
     */
    private static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        return static::withTrashed()
            ->where('slug', $slug)
            ->when($excludeId, fn($query) => $query->where('id', '!=', $excludeId))
            ->exists();
    }

    public static function countUnpublishedEvents($organizerId)
    {
        return self::where('organizer_id', $organizerId)
            ->whereNotIn('status', ['p', 'e']) // Not published or embargoed
            ->count();
    }

    public function nameChangeRequests()
    {
        return $this->morphMany(NameChangeRequest::class, 'requestable');
    }

    /**
     * Create a duplicate of the event
     *
     * @return \App\Models\Event
     */
    public function duplicate()
    {
        // Create new event with duplicated attributes
        $newEvent = $this->replicate(['location_latlon']);
        $newEvent->slug = Str::slug('new-event-' . Str::random(6));
        $newEvent->status = '0'; // Set as draft
        $newEvent->name = $this->name . ' (Copy)';
        $newEvent->published_at = null;
        $newEvent->hasLocation = $this->hasLocation; // Copy the hasLocation flag
        $newEvent->save();

        // Always duplicate location since it's always created
        if ($this->location) {
            $newLocation = $this->location->replicate();
            $newLocation->event_id = $newEvent->id;
            $newLocation->save();
        }

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

        // Duplicate price ranges
        foreach ($this->priceranges as $priceRange) {
            $newPriceRange = $priceRange->replicate();
            $newPriceRange->event_id = $newEvent->id;
            $newPriceRange->save();
        }

        // Duplicate shows and their tickets
        foreach ($this->shows as $show) {
            $newShow = $show->replicate();
            $newShow->event_id = $newEvent->id;
            $newShow->save();

            // Duplicate tickets for this show
            foreach ($show->tickets as $ticket) {
                $newTicket = $ticket->replicate();
                $newTicket->ticket_type = get_class($newShow);
                $newTicket->ticket_id = $newShow->id;
                $newTicket->save();
            }
        }

        // Duplicate images
        foreach ($this->images as $image) {
            $newImage = $image->replicate();
            $newImage->imageable_id = $newEvent->id;
            $newImage->save();
        }

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
            'priceranges',
            'shows.tickets',
            'images',
            'videos'
        ]);
    }

    /**
     * Get tickets from just the first show as an accessor
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFirstShowTicketsAttribute()
    {
        // First check if shows are already loaded to avoid additional query
        if ($this->relationLoaded('shows')) {
            $firstShow = $this->shows->first();
            
            // If the first show exists and tickets are loaded
            if ($firstShow && $firstShow->relationLoaded('tickets')) {
                return $firstShow->tickets;
            }
            
            // If the first show exists but tickets aren't loaded
            if ($firstShow) {
                return $firstShow->tickets()->get();
            }
        }
        
        // Fall back to query if shows aren't loaded
        $firstShow = $this->shows()->with('tickets')->orderBy('date', 'asc')->first();
        return $firstShow ? $firstShow->tickets : collect();
    }

}
