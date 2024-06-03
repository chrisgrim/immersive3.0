<?php

namespace App\Models;

use App\Models\{Conversation, Genre, Organizer, User, Category};
use App\Models\Events\{ Show, PriceRange, Advisory, Location, AgeLimit, InteractiveLevel, RemoteLocation, ContactLevel, ContentAdvisory, MobilityAdvisory, Timezone, ShowOnGoing};
use App\Models\Admin\{ ReviewEvent, StaffPick };
use App\Scopes\PublishedScope;
use App\Traits\{Favoritable};
use Elastic\ScoutDriverPlus\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Carbon\Carbon;


class Event extends Model
{
    use Favoritable, SoftDeletes, Searchable;

    protected $casts = [
        'location_latlon' => 'array',
        'hasLocation' => 'boolean',
    ];

    protected $fillable = [
        'slug', 'user_id', 'timezone_id', 'category_id','interactive_level_id','organizer_id','description','name','largeImagePath','thumbImagePath','advisories_id', 'organizer_id', 'location_latlon', 'closingDate','websiteUrl','ticketUrl','show_times','price_range', 'status','tag_line', 'hasLocation', 'showtype', 'embargo_date', 'remote_description', 'published_at', 'call_to_action', 'age_limits_id', 'rank', 'video', 'archived'
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
        return [
            'name' => $this->name,
            'status' => $this->status,
            'showtype' => $this->showtype,
            'rank' => $this->rank,
            'category_id' => $this->category_id,
            'location_latlon' => $this->location_latlon,
            'hasLocation' => $this->hasLocation,
            'shows' => $this->showsSelect,
            'published_at' => $this->published_at,
            'closingDate' => $this->closingDate,
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
    * Each event belongs to One User
    *
    * @return \Illuminate\Database\Eloquent\Relations\belongsTo
    */
    public function owners() 
    {
        return $this->organizer->allUsers();
    }


    /**
    * Each event has a conversation
    *
    * @return \Illuminate\Database\Eloquent\Relations\belongsTo
    */
    public function conversation() 
    {
        return $this->hasOne(Conversation::class);
    }

    /**
    * Each event belongs to one Category
    *
    * @return \Illuminate\Database\Eloquent\Relations\belongsTo
    */
    public function category() 
    {
        return $this->belongsTo(Category::class);
    }

    /**
    * Each event belongs to one timezone
    *
    * @return \Illuminate\Database\Eloquent\Relations\belongsTo
    */
    public function timezone() 
    {
        return $this->belongsTo(Timezone::class);
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
    * @return \Illuminate\Database\Eloquent\Relations\belongsTo
    */
    public function eventreviews() 
    {
        return $this->hasMany(ReviewEvent::class)
                    ->orderBy('rank', 'ASC');
    }

    /**
    * Each event has many clicks
    *
    * @return \Illuminate\Database\Eloquent\Relations\belongsTo
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
     * Each event has many shows
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function showOnGoing() 
    {
        return $this->hasOne(ShowOnGoing::class);
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
    * Deletes the event images and then deletes event
    *
    * @return Nothing
    */
    public function deleteEvent($event) 
    {
        if ($event->user_id == auth()->id()) {
            if ($event->conversation()->exists()) {
                $event->conversation()->first()->delete();
            }
            $event->delete();
        }
    }

    /**
    * Check if event exists
    *
    * @return boolean
    */
    public function exists($event, $request) 
    {
        return Event::where('slug', Str::slug($request->name))->where('id', '!=', $event->id )->exists();
    }

    /**
    * Update status
    *
    * @return udpates event status
    */
    public function updateEventStatus($status, $request) 
    {
        if ($request->resubmit) {
            $this->update([ 'status' => '8' ]);
        } else {
            if ($this->status < $status + 1 && $this->inProgress()) $this->update([ 'status' => $status ]); 
        }
    }

    /**
    * Check Event Statue
    *
    * @return checks event status and returns boolean
    */
    public function checkEventStatus($status) 
    {
        return $this->status < $status && $this->inProgress();
    }

    /**
    * Deletes the event images and then deletes event
    *
    * @return Nothing
    */
    public function finalizeEvent($event) 
    {
        $website = $event->organizer->website;
        if ($event->websiteUrl == null) {
            $event->update([ 'websiteUrl' => $website ]);
        }
        if ($event->ticketUrl == null) {
            $event->update([ 'ticketUrl' => $website ]);
        }
    }

    /**
    * Deletes the event images and then deletes event
    *
    * @return Nothing
    */
    public static function finalSlug($event) 
    {
        if(Event::withTrashed()->where('slug', '=', Str::slug($event->name))->where('id', '!=', $event->id)->exists()){
            $slug = Str::slug($event->name) . '-' . substr(md5(microtime()),rand(0,26),5);
            if(Event::where('slug', '=', $slug)->exists()){
                return Str::slug($event->name) . '-' . rand(1,50000);
            } else {
                return $slug;
            };
        } else {
            return Str::slug($event->name);
        };
    }

    /**
    * Store a newly created resource in storage. Update all the standard fields. For each genre field I check if they exist then add any the user created. Finally I sync those submitted with the genres associated with the event.
    *
    * @return Nothing
    */
    public function storeGenres($request, $event) 
    {
        if ($request->has('genres')) {
            foreach ($request['genres'] as $genre) {
                Genre::firstOrCreate([
                    'slug' => Str::slug($genre['name'])
                ],
                [
                    'name' => $genre['name'],
                    'user_id' => auth()->user()->id,
                ]);
            };
            $newSync = Genre::whereIn('slug', collect($request['genres'])->map(function ($genre) {
                return Str::slug($genre['name']); 
            })->toArray())->get();
            $event->genres()->sync($newSync);
        };
    }
}
