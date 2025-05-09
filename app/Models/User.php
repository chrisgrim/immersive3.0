<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\View\View;
use Laravel\Cashier\Billable;
use Illuminate\Support\Str;
use App\Models\Events\EventRequest;
use App\Models\Messaging\Conversation;
use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Models\Admin\Dock;
use DB;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password','largeImagePath','thumbImagePath','provider','provider_id', 'gravatar', 'type', 'email_verified_at', 'newsletter_type', 'silence', 'unread', 'reminder', 'current_team_id', 'blurb'
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
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'hasCreatedOrganizers', 'hasMessages', 'hexColor', 'isCurator', 'isAdmin', 'isModerator', 'isUser', 'isCommunityMember'
    ];

    /**
     * The relationships that should be eager loaded.
     *
     * @var array
     */
    protected $with = ['organizer'];

    public function forClientSide()
    {
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
            'organizer' => $this->organizer ? [
                'id' => $this->organizer->id,
                'name' => $this->organizer->name,
            ] : null,
        ];
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
        return $this->teams->contains(function ($t) use ($organizer) {
            return $t->id === $organizer->id;
        }) || $this->ownsOrganization($organizer);
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
        return $this->morphedByMany(Event::class, 'favorited', 'favorites');
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
    public function isAdmin() {
        return $this->type === 'a';
    }

    /**
    * Determine if the current user is Moderator
    *
    * @return bool
    */
    public function isModerator() {
        return $this->type === 'm' || $this->type === 'a';
    }

    /**
    * Determine if the current user is curator
    *
    * @return bool
    */
    public function isCurator() {
        return $this->type === 'c' || $this->type === 'm' || $this->type === 'a';
    }

    /**
    * Determine if the current user is the profile user
    *
    * @return bool
    */
    public function getIsUserAttribute() {
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
        $myarray = ['#2F405F','#DA5E8E','#20B7A6','#749EEB','#1EAA9A']; 
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
        if (!strstr(get_headers($url, 1)[0], '404 Not Found')) {
            $this->update([ 'gravatar' => "https://www.gravatar.com/avatar/$hash?s=180"]);
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
