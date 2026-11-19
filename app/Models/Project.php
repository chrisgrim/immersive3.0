<?php

namespace App\Models;

use App\Enums\CreditDepartment;
use App\Support\Projects\InstanceAddress;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A show run (people see that name): the show itself, wherever it plays.
 * Rows are made only by App\Actions\Projects\LinkEventToProject: when a
 * second run is approved, or at once from a live listing duplicated with
 * "Same show, new run?" (startFrom), which DissolveProject can undo.
 */
class Project extends Model
{
    use HasFactory, SoftDeletes;

    /** What a chosen address may look like: never a released deleted--{id}. */
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    protected $fillable = ['slug', 'name', 'description', 'largeImagePath', 'thumbImagePath', 'organizer_id', 'editor_organizer_id'];

    protected static function booted(): void
    {
        // Gone from the site, it gives its address up, as a deleted event does.
        static::deleted(function (Project $project) {
            if (! $project->isForceDeleting()) {
                $project->releaseSlug();
            }
            // Ended, merged away or undone: a new name waiting for EI has nothing left to rename.
            $project->nameChangeRequests()->where('status', 'pending')->update(['status' => 'withdrawn']);
        });

        // A renamed project keeps its old address as a redirect, and taking an
        // old one back drops that alias.
        static::updated(function (Project $project) {
            if ($project->wasChanged('slug')) {
                SlugRedirect::remember('events/'.$project->getOriginal('slug'), $project);
                SlugRedirect::forget('events/'.$project->slug, $project);
                // A run's old address the show now goes by leads to the show,
                // and one of something gone for good is dropped.
                SlugRedirect::where('path', 'events/'.$project->slug)->get()
                    ->filter(fn (SlugRedirect $redirect) => $redirect->isStale())
                    ->each->delete();
                SlugRedirect::where('path', 'events/'.$project->slug)
                    ->where('redirectable_type', (new Event)->getMorphClass())
                    ->whereIn('redirectable_id', Event::withTrashed()->where('project_id', $project->id)->select('id'))
                    ->delete();
                $project->settleRunsAfterMoveFrom();
            }
        });
    }

    /**
     * The new address can put a run on a path reserved for something else: a
     * run merged in from a show that once went by this name. An explicit
     * redirect always wins, so the run takes a free address, and its old one
     * is written out, because the route's swap of a former show address would
     * otherwise land on what the reservation points at.
     */
    public function settleRunsAfterMoveFrom(): void
    {
        foreach ($this->runs()->get() as $run) {
            $path = "events/{$this->slug}/{$run->instance_slug}";

            if (! SlugRedirect::pointsElsewhere($path, $run->getMorphClass(), $run->id)) {
                SlugRedirect::forget($path, $run);

                continue;
            }

            // Every address the show has gone by, not only the last: the route
            // would swap any of them onto the reserved path. Never over another
            // record's reservation.
            $this->reserveFormerPaths($run, $run->instance_slug);

            $run->setRelation('project', $this);
            $run->instance_slug = InstanceAddress::allocate($run, $this);
            $run->save();

            SlugRedirect::forget($run->addressPath(), $run);
        }
    }

    /**
     * Writes out $instance under every address this show has gone by, for
     * $run, never over another record's reservation. A former show address is
     * only an alias, and going back to it drops the alias: without these rows
     * its nested paths would answer for the show, then for the next run there.
     */
    public function reserveFormerPaths(Event $run, string $instance): void
    {
        $former = SlugRedirect::where('redirectable_type', $this->getMorphClass())
            ->where('redirectable_id', $this->id)
            ->pluck('path')
            ->filter(fn (string $path) => substr_count($path, '/') === 1);

        foreach ($former as $path) {
            if (! SlugRedirect::pointsElsewhere("{$path}/{$instance}", $run->getMorphClass(), $run->id)) {
                SlugRedirect::remember("{$path}/{$instance}", $run);
            }
        }
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    /** Swap this (already soft-deleted) project's slug for its released one. */
    public function releaseSlug(): void
    {
        $slug = Event::releasedSlug($this->id);

        $released = static::withTrashed()->whereKey($this->id)->whereNotNull('deleted_at')->update(['slug' => $slug]);

        if ($released) {
            $this->slug = $slug;
            $this->syncOriginalAttribute('slug');
        }
    }

    public function publicPath(): string
    {
        return '/events/'.$this->slug;
    }

    /** Made by. */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    /** Keeps the page, when that is not the maker. */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'editor_organizer_id');
    }

    /** The company that keeps the page: the named editor, otherwise the maker. */
    public function keeper(): ?Organizer
    {
        return $this->editor_organizer_id ? $this->editor : $this->organizer;
    }

    /** The instances: every listing linked to this project. */
    public function runs(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function credits(): MorphMany
    {
        return $this->morphMany(Credit::class, 'creditable');
    }

    /** The show carries only its writers. */
    public function writers(): MorphMany
    {
        return $this->credits()->where('department', CreditDepartment::Writing->value);
    }

    public function linkRequests(): HasMany
    {
        return $this->hasMany(ProjectLinkRequest::class);
    }

    /**
     * A show someone may hear about: one with a published run, or one their
     * company keeps or made. Anything else is unannounced.
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        if ($viewer?->isModerator()) {
            return $query;
        }

        $own = $viewer ? [...$viewer->teams()->pluck('organizers.id'), ...$viewer->organizers()->pluck('id')] : [];

        return $query->where(fn ($q) => $q
            ->whereHas('runs', fn ($runs) => $runs->where('status', Event::STATUS_PUBLISHED))
            ->orWhereIn('organizer_id', $own)
            ->orWhereIn('editor_organizer_id', $own));
    }

    /** The page is public only while a run is published. */
    public function isPublic(): bool
    {
        return $this->runs()->where('status', Event::STATUS_PUBLISHED)->exists();
    }

    /**
     * Whether the address is out of reach for this project. See slugHolder().
     *
     * @param  Event|array<int, Event|null>|null  $first  listings taking it over (exempt)
     */
    public static function slugTaken(string $slug, Event|array|null $first = null, ?int $projectId = null): bool
    {
        return static::slugHolder($slug, $first, $projectId) !== null;
    }

    /**
     * Who holds the address, in words for the moderator ("the listing
     * \"X\""), or null when this project may have it: another project has it,
     * an event does, or an old address of something else reserves it.
     *
     * Never a clash: $first (the listings whose address a new show takes
     * over, a joining run included), this project's own runs (holding it as
     * their slug, or as an old address from before they joined: visitors
     * land on the show either way, as EventController::show looks for the
     * project first), this project's own old addresses, and old addresses of
     * something gone for good (SlugRedirect::isStale). Deleted projects
     * count, though they all hold a released slug by then.
     *
     * @param  Event|array<int, Event|null>|null  $first
     */
    public static function slugHolder(string $slug, Event|array|null $first = null, ?int $projectId = null): ?string
    {
        $exempt = collect(is_array($first) ? $first : [$first])->filter()->map(fn (Event $event) => $event->id)->values()->all();
        $runs = fn () => Event::withTrashed()->where('project_id', (int) $projectId)->pluck('id')->all();

        $show = static::withTrashed()->where('slug', $slug)->when($projectId, fn ($q) => $q->whereKeyNot($projectId))->first(['id', 'name']);
        if ($show) {
            return 'the show "'.$show->name.'"';
        }

        $listing = Event::withTrashed()
            ->where('slug', $slug)
            ->whereNotIn('id', $exempt)
            ->when($projectId, fn ($q) => $q->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', '!=', $projectId)))
            ->first(['id', 'name']);
        if ($listing) {
            return 'the listing "'.$listing->name.'"';
        }

        $redirect = SlugRedirect::where('path', 'events/'.$slug)->first();
        if ($redirect === null || $redirect->isStale()) {
            return null;
        }

        $isEvent = $redirect->redirectable_type === (new Event)->getMorphClass();
        $own = ($redirect->redirectable_type === (new self)->getMorphClass() && $projectId && (int) $redirect->redirectable_id === $projectId)
            || ($isEvent && in_array((int) $redirect->redirectable_id, $exempt, true))
            || ($isEvent && $projectId && in_array((int) $redirect->redirectable_id, $runs(), true));
        if ($own) {
            return null;
        }

        $target = $redirect->target();

        return 'an old address of '.($target instanceof self ? 'the show' : 'the listing').' "'.($target->name ?? 'something else').'"';
    }

    /**
     * The address a new project starts with: normally the first listing's
     * own, which it takes over. When that listing only has a disambiguated
     * address because the plain name is someone else's, the project goes by
     * the show's name plus the company's. A moderator can choose another.
     */
    public static function addressFor(Event $first, Organizer $maker, ?Event $joining = null): string
    {
        $base = Slug::base($first->name, 'project');
        $mine = [$first, $joining];

        // The plain name belongs to the listing joining with it (the first
        // one only got a disambiguated address because of it): the show takes it.
        if ($joining && $joining->slug === $base && $first->slug !== $base && ! static::slugTaken($base, $mine)) {
            return $base;
        }

        $disambiguated = Str::startsWith($first->slug, $base.'-') && static::slugTaken($base, $first);

        if (! $disambiguated && ! static::slugTaken($first->slug, $mine)) {
            return $first->slug;
        }

        $company = trim($base.'-'.Str::slug($maker->name), '-');
        $slug = $company;
        $count = 2;

        while (static::slugTaken($slug, $mine)) {
            $slug = $company.'-'.$count;
            $count++;
        }

        return $slug;
    }

    /** A keeper's new name waits for EI (NameChangeRequestService), as an event's does. */
    public function nameChangeRequests()
    {
        return $this->morphMany(NameChangeRequest::class, 'requestable');
    }
}
