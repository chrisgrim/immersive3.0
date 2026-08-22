<?php

namespace App\Models;

use App\Models\Admin\Dock;
use App\Models\Admin\StaffPick;
use App\Models\Components\Favorite;
use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Models\Messaging\Conversation;
use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Billable;
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'largeImagePath', 'thumbImagePath', 'provider', 'provider_id', 'gravatar', 'type', 'email_verified_at', 'newsletter_type', 'silence', 'unread', 'reminder', 'current_team_id', 'blurb', 'notification_preferences', 'legal_first_name', 'legal_last_name', 'phone', 'privacy_preferences',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        // Account Settings' Personal Information reads these as plain PHP
        // properties (hidden only affects serialization, not access) — but a
        // generic `{{ $user }}`/toJson() anywhere else (e.g. the profile page)
        // must never leak them to a viewer who isn't the account owner.
        'legal_first_name',
        'legal_last_name',
        'phone',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'notification_preferences' => 'array',
        'privacy_preferences' => 'array',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    // hasCreatedOrganizers / hasMessages / isCommunityMember each run a query
    // and were appended, so they N+1'd on every serialized User collection
    // (admin user lists, inbox participants, curators, reviews). They're only
    // ever read off the logged-in user, which forClientSide() builds explicitly
    // via direct accessor calls — so they stay off $appends. Call the accessor
    // directly where needed. (Appends-N+1 sweep.)
    protected $appends = [
        'hexColor', 'isCurator', 'isAdmin', 'isModerator', 'isUser',
    ];

    /**
     * The relationships that should be eager loaded.
     *
     * @var array
     */
    protected $with = ['organizer'];

    public function forClientSide()
    {
        // Get the appropriate organizer for the frontend
        $organizer = $this->getCurrentOrganizer();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'hexColor' => $this->hexColor,
            'hasMessages' => $this->hasMessages,
            'thumbImagePath' => $this->thumbImagePath,
            'isModerator' => $this->type === 'm' || $this->type === 'a',
            'isAdmin' => $this->type === 'a',
            'isCurator' => $this->type === 'c' || $this->type === 'm' || $this->type === 'a',
            'isCommunityMember' => $this->isCommunityMember,
            'unread' => $this->unread,
            'hasCreatedOrganizers' => $this->hasCreatedOrganizers,
            'organizer' => $organizer ? [
                'id' => $organizer->id,
                'name' => $organizer->name,
            ] : null,
        ];
    }

    public function getCurrentOrganizer()
    {
        // First try current_team_id if it's set and valid
        if ($this->current_team_id) {
            $currentOrganizer = Organizer::find($this->current_team_id);
            if ($currentOrganizer && ($this->ownsOrganization($currentOrganizer) || $this->belongsToOrganization($currentOrganizer))) {
                return $currentOrganizer;
            }
        }

        // Fallback to their first owned organizer
        $ownedOrganizer = $this->organizers()->first();
        if ($ownedOrganizer) {
            // Auto-update current_team_id if it was invalid
            if ($this->current_team_id !== $ownedOrganizer->id) {
                $this->update(['current_team_id' => $ownedOrganizer->id]);
            }

            return $ownedOrganizer;
        }

        // Final fallback: any team they're a member of
        $membershipOrganizer = $this->teams()->first();
        if ($membershipOrganizer) {
            // Auto-update current_team_id if it was invalid
            if ($this->current_team_id !== $membershipOrganizer->id) {
                $this->update(['current_team_id' => $membershipOrganizer->id]);
            }

            return $membershipOrganizer;
        }

        // No organizers at all - clear current_team_id
        if ($this->current_team_id) {
            $this->update(['current_team_id' => null]);
        }

        return null;
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function organizer()
    {
        return $this->hasOne(Organizer::class, 'id', 'current_team_id');
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function getImageAttribute()
    {
        return $this->images()->first();
    }

    public function organizers()
    {
        return $this->hasMany(Organizer::class)
            ->orderBy('created_at', 'DESC');
    }

    /**
     * Get all teams/organizations the user belongs to
     */
    public function teams()
    {
        return $this->belongsToMany(Organizer::class, 'organizer_user')
            ->withPivot('role')
            ->as('membership')  // This names the pivot relationship
            ->orderBy('organizers.created_at', 'desc');
    }

    /**
     * Determine if the user owns the given organization.
     *
     * @param  mixed  $team
     * @return bool
     */
    public function ownsOrganization($organizer)
    {
        return $this->id == $organizer->user_id;
    }

    /**
     * Determine if the user belongs to the given organization.
     *
     * @param  mixed  $team
     * @return bool
     */
    public function belongsToOrganization($organizer)
    {
        return $this->teams()->whereKey($organizer->id)->exists()
            || $this->ownsOrganization($organizer);
    }

    /**
     * The User can send many messages
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The User has many docks
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function docks()
    {
        return $this->hasMany(Dock::class);
    }

    /**
     * The User can send many messages
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function modmessages()
    {
        return $this->hasMany(ModeratorComment::class);
    }

    /**
     * The User belongs to many conversations
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class)->orderBy('updated_at', 'DESC')->whereNull('event_id');
    }

    /**
     * The User belongs to many conversations
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function eventconversations()
    {
        return $this->belongsToMany(Conversation::class)->orderBy('updated_at', 'DESC')->whereNotNull('event_id');
    }

    /**
     * The communities that belong to the user.
     */
    public function communities()
    {
        return $this->belongsToMany(Community::class);
    }

    /**
     * The User has many Staff Picks
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function staffpicks()
    {
        return $this->hasMany(StaffPick::class);
    }

    /**
     * The User has many Staff Picks
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * User can have many favorites
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function favouritedEvents()
    {
        return $this->morphedByMany(Event::class, 'favorited', 'favorites')->withTimestamps();
    }

    public function followedOrganizers()
    {
        return $this->belongsToMany(Organizer::class, 'organizer_followers')->withTimestamps();
    }

    public function savedSearches()
    {
        return $this->hasMany(SavedSearch::class);
    }

    /**
     * Each User has One Location
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function location()
    {
        return $this->hasOne(UserLocation::class);
    }

    /**
     * Determine if the current user is Admin
     *
     * @return bool
     */
    public function isAdmin()
    {
        return $this->type === 'a';
    }

    /**
     * Whether this user wants to receive a given admin notification type.
     * Opt-OUT model: a missing key (or no preferences at all) means subscribed,
     * so newly-added notification types default to ON without a migration/backfill.
     */
    public function wantsNotification(string $key, bool $default = true): bool
    {
        $prefs = $this->notification_preferences ?? [];

        // Missing key → $default (true = opt-out, existing admin-alert keys; false = opt-in,
        // for newer user-facing keys). A present value is normalized strictly so a stray
        // "false" string from a manual write can't re-enable it.
        if (! array_key_exists($key, $prefs)) {
            return $default;
        }

        return filter_var($prefs[$key], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether this user has opted a given piece of profile content into
     * public visibility (e.g. followed organizers, saved-events count) —
     * opt-OUT model: a missing key defaults to ON/public, so a user who's
     * never touched their Privacy settings shows both by default and has
     * to explicitly turn each one off to hide it.
     */
    public function showsPublicly(string $key): bool
    {
        $prefs = $this->privacy_preferences ?? [];

        if (! array_key_exists($key, $prefs)) {
            return true;
        }

        return filter_var($prefs[$key], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Determine if the current user is Moderator
     *
     * @return bool
     */
    public function isModerator()
    {
        return $this->type === 'm' || $this->type === 'a';
    }

    /**
     * Determine if the current user is curator
     *
     * @return bool
     */
    public function isCurator()
    {
        return $this->type === 'c' || $this->type === 'm' || $this->type === 'a';
    }

    /**
     * Determine if the current user is the profile user
     *
     * @return bool
     */
    public function getIsUserAttribute()
    {
        return $this->id === auth()->id();
    }

    /**
     * Determine if the current user has created more than one organizer
     *
     * @return bool
     */
    public function getHasCreatedOrganizersAttribute()
    {
        $totalCount = $this->teams()->count();

        return $totalCount > 0;
    }

    /**
     * Assign the current user a color
     *
     * @return string
     */
    public function gethexColorAttribute()
    {
        $myarray = ['#2F405F', '#DA5E8E', '#20B7A6', '#749EEB', '#1EAA9A'];

        return $myarray[$this->id % count($myarray)];
    }

    /**
     * Determine if the current user has messages
     *
     * @return bool
     */
    public function getHasMessagesAttribute()
    {
        return DB::table('conversations')
            ->where('user_one', $this->id)
            ->orWhere('user_two', $this->id)
            ->count() ? true : false;
    }

    /**
     * Determine if the user has any unread messages
     *
     * @return bool
     */
    public function hasUnreadMessages()
    {
        return $this->unread === 'm';
    }

    /**
     * Determine if the current user has messages
     *
     * @return bool
     */
    public function getisCuratorAttribute()
    {
        return $this->type === 'c' || $this->type === 'm' || $this->type === 'a';
    }

    /**
     * Determine if the current user has messages
     *
     * @return bool
     */
    public function getisAdminAttribute()
    {
        return $this->type === 'a';
    }

    /**
     * Determine if the current user has messages
     *
     * @return bool
     */
    public function getisModeratorAttribute()
    {
        return $this->type === 'm' || $this->type === 'a';
    }

    public function getGravatar()
    {
        $hash = md5(strtolower(trim($this->email)));
        $url = "https://www.gravatar.com/avatar/$hash?d=404";
        if (! strstr(get_headers($url, 1)[0], '404 Not Found')) {
            $this->update(['gravatar' => "https://www.gravatar.com/avatar/$hash?s=180"]);
        }
    }

    /**
     * Determine if the user is a curator for any community
     *
     * @return bool
     */
    public function isCommunityMember()
    {
        return $this->communities()->exists() ||
               $this->type === 'm' ||
               $this->type === 'a';
    }

    /**
     * Get the attribute for checking if user is in any community
     *
     * @return bool
     */
    public function getIsCommunityMemberAttribute()
    {
        return $this->communities()->exists() ||
               $this->type === 'm' ||
               $this->type === 'a';
    }
}
