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
 * The show itself, wherever it plays. A row is made only by
 * App\Actions\Projects\LinkEventToProject, when a second run is approved.
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
        });

        // A renamed project keeps its old address as a redirect, and taking an
        // old one back drops that alias.
        static::updated(function (Project $project) {
            if ($project->wasChanged('slug')) {
                SlugRedirect::remember('events/'.$project->getOriginal('slug'), $project);
                SlugRedirect::forget('events/'.$project->slug, $project);
                $project->settleRunsAfterMoveFrom($project->getOriginal('slug'));
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
    public function settleRunsAfterMoveFrom(string $oldSlug): void
    {
        foreach ($this->runs()->get() as $run) {
            $path = "events/{$this->slug}/{$run->instance_slug}";

            if (! SlugRedirect::pointsElsewhere($path, $run->getMorphClass(), $run->id)) {
                SlugRedirect::forget($path, $run);

                continue;
            }

            SlugRedirect::remember("events/{$oldSlug}/{$run->instance_slug}", $run);

            $run->setRelation('project', $this);
            $run->instance_slug = InstanceAddress::allocate($run, $this);
            $run->save();

            SlugRedirect::forget($run->addressPath(), $run);
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
     * Whether the address is out of reach for this project: another project
     * has it, an event does ($first, whose address it is taking over, and a
     * first listing still holding an address this project has gone by, are
     * the exceptions), or an old address of something else reserves it.
     * Deleted projects count, though they all hold a released slug by then.
     */
    public static function slugTaken(string $slug, ?Event $first = null, ?int $projectId = null): bool
    {
        // The one permitted collision: only on an address that is, or was, the project's.
        $ownAddress = $projectId !== null && (
            static::whereKey($projectId)->where('slug', $slug)->exists()
            || SlugRedirect::where('path', 'events/'.$slug)
                ->where('redirectable_type', (new self)->getMorphClass())
                ->where('redirectable_id', $projectId)
                ->exists()
        );

        return static::withTrashed()->where('slug', $slug)->when($projectId, fn ($q) => $q->whereKeyNot($projectId))->exists()
            || Event::withTrashed()
                ->where('slug', $slug)
                ->when($first, fn ($q) => $q->whereKeyNot($first->id))
                ->when($ownAddress, fn ($q) => $q->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', '!=', $projectId)))
                ->exists()
            || SlugRedirect::where('path', 'events/'.$slug)
                ->where(fn ($q) => $q
                    ->where(fn ($q) => $q->where('redirectable_type', '!=', (new self)->getMorphClass())->orWhere('redirectable_id', '!=', (int) $projectId))
                    ->where(fn ($q) => $q->where('redirectable_type', '!=', (new Event)->getMorphClass())->orWhere('redirectable_id', '!=', (int) $first?->id)))
                ->exists();
    }

    /**
     * The address a new project starts with: normally the first listing's
     * own, which it takes over. When that listing only has a disambiguated
     * address because the plain name is someone else's, the project goes by
     * the show's name plus the company's. A moderator can choose another.
     */
    public static function addressFor(Event $first, Organizer $maker): string
    {
        $base = Slug::base($first->name, 'project');
        $disambiguated = Str::startsWith($first->slug, $base.'-') && static::slugTaken($base, $first);

        if (! $disambiguated && ! static::slugTaken($first->slug, $first)) {
            return $first->slug;
        }

        $company = trim($base.'-'.Str::slug($maker->name), '-');
        $slug = $company;
        $count = 2;

        while (static::slugTaken($slug, $first)) {
            $slug = $company.'-'.$count;
            $count++;
        }

        return $slug;
    }
}
