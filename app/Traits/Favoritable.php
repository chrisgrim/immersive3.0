<?php

namespace App\Traits;

use App\Models\Components\Favorite;
use Illuminate\Database\Eloquent\Model;

trait Favoritable
{
    /**
     * Boot the trait.
     */
    protected static function bootFavoritable()
    {
        static::deleting(function ($model) {
            $model->favorites->each->delete();
        });
    }

    /**
     * A reply can be favorited.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function favorites()
    {
        return $this->morphMany(Favorite::class, 'favorited');
    }

    /**
     * The current user's favorite of this model (0 or 1 rows).
     *
     * Eager-load THIS (not `favorites`) on listings: isFavorited only needs to
     * know whether the current user favorited the row, so pulling the whole
     * favorites set per row is wasteful on popular events. Keep `favorites`
     * for anything that needs the full set (e.g. the delete cascade).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function currentUserFavorite()
    {
        return $this->favorites()->where('user_id', auth()->id());
    }

    /**
     * Favorite the current reply.
     *
     * @return Model
     */
    public function favorite()
    {
        $attributes = ['user_id' => auth()->id()];
        if (! $this->favorites()->where($attributes)->exists()) {
            return $this->favorites()->create($attributes);
        }
    }

    /**
     * Unfavorite the current reply.
     */
    public function unfavorite()
    {
        $attributes = ['user_id' => auth()->id()];
        $this->favorites()->where($attributes)->get()->each->delete();
    }

    /**
     * Determine if the current reply has been favorited.
     *
     * @return bool
     */
    public function isFavorited()
    {
        $userId = auth()->id();

        if (! $userId) {
            return false;
        }

        // Prefer the current-user-scoped relation when it's been eager-loaded
        // (the optimized path used by listings). Fall back to a full favorites
        // collection if that's what was loaded, else a targeted exists() query
        // rather than hydrating every favorite row.
        if ($this->relationLoaded('currentUserFavorite')) {
            return $this->currentUserFavorite->isNotEmpty();
        }

        if ($this->relationLoaded('favorites')) {
            return $this->favorites->where('user_id', $userId)->isNotEmpty();
        }

        return $this->favorites()->where('user_id', $userId)->exists();
    }

    /**
     * Fetch the favorited status as a property.
     *
     * @return bool
     */
    public function getIsFavoritedAttribute()
    {
        return $this->isFavorited();
    }

    /**
     * Get the number of favorites for the reply.
     *
     * @return int
     */
    public function getFavoritesCountAttribute()
    {
        return $this->favorites->count();
    }
}
