<?php

namespace App\Models;

use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One row per person, credited on as many shows and runs as they work on. */
class Artist extends Model
{
    use HasFactory;

    public const PROPOSED = 'proposed';

    public const APPROVED = 'approved';

    protected $fillable = ['slug', 'name', 'bio', 'largeImagePath', 'thumbImagePath', 'status', 'merged_into_id', 'user_id'];

    protected static function booted(): void
    {
        static::creating(function (Artist $artist) {
            if (! $artist->slug) {
                $artist->slug = static::uniqueSlug($artist->name);
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    /** Artists have no public page yet; this is the address they will have. */
    public function publicPath(): string
    {
        return '/artists/'.$this->slug;
    }

    private static function uniqueSlug(?string $name): string
    {
        $base = Slug::base($name, 'artist');
        $slug = $base;
        $count = 2;

        while (static::where('slug', $slug)->exists() || SlugRedirect::where('path', 'artists/'.$slug)->exists()) {
            $slug = $base.'-'.$count;
            $count++;
        }

        return $slug;
    }

    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
