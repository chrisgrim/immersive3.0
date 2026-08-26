<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Orders events newest-published-first. That is ALL it does.
 *
 * It applies no WHERE clause and filters nothing — an unpublished event is
 * still returned by any query carrying this scope. Anything that must show
 * only published events needs its own explicit `where('status', 'p')` (see
 * FavoriteController::store(), FollowController::store(),
 * EventAttributesController's eligibility query).
 *
 * Named for the ordering deliberately. It used to be called PublishedScope,
 * which read as a visibility filter and was repeatedly assumed to be one —
 * FavoriteController had to add an explicit status check after draft and
 * embargoed events turned out to be favouritable through it, and three
 * separate files grew comments warning that the name did not describe the
 * behaviour. Renamed 2026-08-26; the behaviour is unchanged.
 *
 * Because it is a global scope, the ORDER BY is reapplied at execute time no
 * matter what reorder() did earlier in a chain, so a DISTINCT query that
 * doesn't select published_at must drop it with
 * withoutGlobalScope(LatestPublishedFirstScope::class) or MySQL rejects the
 * query outright.
 */
class LatestPublishedFirstScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $builder->orderBy('published_at', 'desc');
    }
}
