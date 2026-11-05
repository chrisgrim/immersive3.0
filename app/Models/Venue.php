<?php

namespace App\Models;

use App\Models\Events\Location;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A real place people go to. A listing's locations row keeps its copied address. */
class Venue extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    protected $fillable = ['slug', 'name', 'room', 'home', 'street', 'city', 'region', 'region_long', 'country', 'country_long', 'postal_code', 'longitude', 'latitude', 'description', 'directions', 'largeImagePath', 'thumbImagePath', 'status', 'merged_into_id'];

    protected static function booted(): void
    {
        static::creating(function (Venue $venue) {
            if (! $venue->slug) {
                $venue->slug = static::uniqueSlug($venue->name);
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
        return static::where('slug', $slug)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists()
            || SlugRedirect::pointsElsewhere('venues/'.$slug, (new self)->getMorphClass(), $exceptId);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }
}
