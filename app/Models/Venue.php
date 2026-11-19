<?php

namespace App\Models;

use App\Models\Events\Location;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** A real place people go to. A listing's locations row keeps its copied address. */
class Venue extends Model
{
    use HasFactory;

    /** Being added through Add a venue and not sent yet: only its keeper (and staff) see it. */
    public const DRAFT = 'draft';

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    protected $casts = ['claimed_at' => 'datetime', 'amenities' => 'array'];

    protected $fillable = ['slug', 'name', 'room', 'home', 'street', 'city', 'region', 'region_long', 'country', 'country_long', 'postal_code', 'longitude', 'latitude', 'description', 'directions', 'largeImagePath', 'thumbImagePath', 'status', 'merged_into_id', 'website', 'email', 'phone', 'instagramHandle', 'facebookHandle', 'twitterHandle', 'tiktokHandle', 'amenities'];

    protected static function booted(): void
    {
        static::creating(function (Venue $venue) {
            if (! $venue->slug) {
                $venue->slug = static::uniqueSlug($venue->name);
            }
        });

        // Taking the name of a venue merged into it: that row gives the slug
        // up first (slugs are unique), and keeps a released one. One way: an
        // unmerge, if ever built, must give the merged venue a fresh uniqueSlug. Its address
        // already redirects here (MergeVenues), and forget() below drops that.
        static::updating(function (Venue $venue) {
            if ($venue->isDirty('slug')) {
                static::where('slug', $venue->slug)->where('merged_into_id', $venue->id)->get()
                    ->each(fn (Venue $merged) => $merged->updateQuietly(['slug' => 'merged--'.$merged->id]));
            }
        });

        // Renamed, it keeps its old address as a redirect.
        static::updated(function (Venue $venue) {
            if ($venue->wasChanged('slug')) {
                SlugRedirect::remember('venues/'.$venue->getOriginal('slug'), $venue);
                SlugRedirect::forget('venues/'.$venue->slug, $venue);
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function publicPath(): string
    {
        return '/venues/'.$this->slug;
    }

    /** With its room, when it has one. */
    public function displayName(): string
    {
        return $this->room ? "{$this->name}, {$this->room}" : $this->name;
    }

    /** The venue this one ended up merged into, or itself. */
    public function winner(): self
    {
        $venue = $this;
        $seen = [];

        while ($venue->merged_into_id && ! isset($seen[$venue->id]) && ($next = self::find($venue->merged_into_id))) {
            $seen[$venue->id] = true;
            $venue = $next;
        }

        return $venue;
    }

    /** Refuses live venues and old addresses of anything but $exceptId. */
    public static function uniqueSlug(?string $name, ?int $exceptId = null): string
    {
        $base = Slug::base($name, 'venue');
        $slug = $base;
        $count = 2;

        while (static::slugTaken($slug, $exceptId)) {
            $slug = $base.'-'.$count;
            $count++;
        }

        return $slug;
    }

    private static function slugTaken(string $slug, ?int $exceptId): bool
    {
        // A venue merged into this one holds its old name for it (its page
        // redirects here): no clash, and it gives the slug up when this one
        // takes it (the updating hook).
        return static::where('slug', $slug)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId)
                ->where(fn ($q) => $q->whereNull('merged_into_id')->orWhere('merged_into_id', '!=', $exceptId)))
            ->exists()
            || SlugRedirect::pointsElsewhere('venues/'.$slug, (new self)->getMorphClass(), $exceptId);
    }

    /**
     * What a person may see or pick: approved venues, and pending ones proposed
     * by someone on a team they are on. A pending venue's address is its
     * proposer's to share until a moderator approves it. Moderators see all.
     */
    /**
     * The venues a person keeps, as VenuePolicy lets them edit: the ones they own,
     * and the unclaimed ones a team of theirs added. Merged venues are gone.
     */
    public function scopeKeptBy(Builder $query, User $user): Builder
    {
        // Subqueries, so this stays one statement (it runs for the menu on every page).
        return $query->whereNull('merged_into_id')
            ->where(fn ($kept) => $kept->where('user_id', $user->id)
                ->orWhere(fn ($added) => $added->whereNull('user_id')->where(fn ($team) => $team
                    ->whereIn('organizer_id', fn ($member) => $member->select('organizer_id')->from('organizer_user')->where('user_id', $user->id))
                    ->orWhereIn('organizer_id', fn ($owner) => $owner->select('id')->from('organizers')->where('user_id', $user->id)))));
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->isModerator()) {
            return $query;
        }

        $teams = fn ($sub) => $sub->select('organizer_id')->from('organizer_user')->where('user_id', $user?->id);

        return $this->approvedOrProposedBy($query, $teams);
    }

    /** The same for a listing: approved, or proposed by someone on its organizer's team. */
    public function scopeVisibleToOrganizer(Builder $query, ?int $organizerId): Builder
    {
        return $this->approvedOrProposedBy($query, fn ($sub) => $sub->selectRaw('?', [$organizerId ?? 0]));
    }

    /** $organizers: a subquery selecting organizer ids. */
    private function approvedOrProposedBy(Builder $query, \Closure $organizers): Builder
    {
        return $query->where(fn ($q) => $q->where('venues.status', self::APPROVED)
            // The team keeping a waiting venue sees it, whoever proposed it
            // (staff often add one for a company's listing).
            ->orWhere(fn ($kept) => $kept->where('venues.status', self::PENDING)->whereIn('venues.organizer_id', $organizers))
            ->orWhereExists(fn ($proposed) => $proposed->selectRaw('1')->from('audit_logs')
                ->where('audit_logs.subject_type', $this->getMorphClass())
                ->whereColumn('audit_logs.subject_id', 'venues.id')
                ->where('audit_logs.action', 'venue.proposed')
                ->whereIn('audit_logs.user_id', fn ($members) => $members->select('user_id')->from('organizer_user')
                    ->whereIn('organizer_id', $organizers))));
    }

    /**
     * The venue page's contact rows: [sprite icon, label, link], only the ones set.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public function contactLinks(): array
    {
        $handle = fn (?string $value) => ltrim((string) $value, '@');

        return array_values(array_filter([
            $this->website ? ['ri-global-line', preg_replace('#^https?://(www\.)?#', '', rtrim($this->website, '/')), $this->website] : null,
            // Only once the venue is its own (claimed, or added by its team): an
            // address EI found for a venue nobody has claimed stays with staff,
            // not printed for spam bots to collect.
            $this->email && $this->showsEmail() ? ['ri-mail-line', $this->email, 'mailto:'.$this->email] : null,
            $this->phone ? ['ri-chat-2-line', $this->phone, 'tel:'.preg_replace('/[^0-9+]/', '', $this->phone)] : null,
            $this->instagramHandle ? ['ri-instagram-line', '@'.$handle($this->instagramHandle), 'https://instagram.com/'.rawurlencode($handle($this->instagramHandle))] : null,
            $this->facebookHandle ? ['ri-facebook-line', $this->facebookHandle, 'https://facebook.com/'.rawurlencode($this->facebookHandle)] : null,
            $this->tiktokHandle ? ['ri-global-line', 'TikTok @'.$handle($this->tiktokHandle), 'https://www.tiktok.com/@'.rawurlencode($handle($this->tiktokHandle))] : null,
            $this->twitterHandle ? ['ri-twitter-line', '@'.$handle($this->twitterHandle), 'https://x.com/'.rawurlencode($handle($this->twitterHandle))] : null,
        ]));
    }

    /**
     * The email is shown once the venue speaks for itself: claimed, added by
     * its own team, or kept by the person who added it. A venue EI staff keep
     * until its owner claims it (imported) does not show one.
     */
    public function showsEmail(): bool
    {
        if ($this->claimed_at !== null || $this->organizer_id !== null) {
            return true;
        }

        return $this->user_id !== null && ! in_array($this->ownerUser?->type, ['m', 'a'], true);
    }

    /** Open to a claim until one is approved; a pending or merged venue is not. */
    /** Who looks after it day to day: its owner, or (unowned) a member of the team that added it. Not staff. */
    public function isKeptBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->user_id !== null) {
            return (int) $this->user_id === (int) $user->id;
        }

        return $this->owner !== null && $user->belongsToOrganization($this->owner);
    }

    public function isClaimable(): bool
    {
        // Never claimed, or claimed by someone whose account is gone since
        // (user_id nulls on delete): either way the real venue can ask again.
        // Live, or still in review (someone adding the same place can claim it
        // rather than add it twice); never a draft or a merged one.
        return in_array($this->status, [self::APPROVED, self::PENDING], true) && $this->merged_into_id === null
            && ($this->claimed_at === null || $this->user_id === null);
    }

    /** The person who owns the venue once a claim is approved. */
    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(VenueClaim::class);
    }

    /** The team that keeps this venue's page: whoever added it, until the venue claims it. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /** A live venue's new name waits for EI (NameChangeRequestService). */
    public function nameChangeRequests()
    {
        return $this->morphMany(NameChangeRequest::class, 'requestable');
    }
}
