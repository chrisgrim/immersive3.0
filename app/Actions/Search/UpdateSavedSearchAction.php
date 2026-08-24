<?php

namespace App\Actions\Search;

use App\Exceptions\DuplicateSavedSearchException;
use App\Models\SavedSearch;
use Illuminate\Database\QueryException;

/**
 * Deliberate edit of one exact saved-search row, from the Hub's Saved Search
 * Preferences editor — distinct from SaveSearchAction (auto-save on every
 * nav search, which overwrites whichever row is the user's rotating
 * unpinned slot). This updates the specific row the caller passed in,
 * regardless of its pin state, and never touches any other row.
 */
class UpdateSavedSearchAction
{
    public function __construct(private NormalizeSavedSearchCriteriaAction $normalizeCriteria) {}

    /**
     * @throws DuplicateSavedSearchException if the edited criteria now match
     *                                       another of the user's saved searches.
     */
    public function handle(SavedSearch $savedSearch, string $name, array $criteria): SavedSearch
    {
        // Server-side enforcement of mode-exclusivity, not just a UI
        // convention — a location search never carries a remoteLocation
        // slug, and an at-home search never carries city/lat/lng/live,
        // regardless of what the request happened to include.
        if (($criteria['searchType'] ?? null) === 'inPerson') {
            $criteria['remoteLocation'] = null;
        } elseif (($criteria['searchType'] ?? null) === 'atHome') {
            $criteria['city'] = null;
            $criteria['lat'] = null;
            $criteria['lng'] = null;
            $criteria['live'] = false;
            $criteria['NElat'] = null;
            $criteria['NElng'] = null;
            $criteria['SWlat'] = null;
            $criteria['SWlng'] = null;
        }

        $normalized = $this->normalizeCriteria->handle($criteria);
        $fingerprint = hash('sha256', json_encode($normalized));

        // Same per-user lock as SaveSearchAction's own read-decide-write
        // sequence — without it, a concurrent auto-save's eviction could
        // select THIS exact row as "oldest scratch" before this edit
        // commits is_scratch=false, then overwrite its name/criteria/
        // fingerprint right after, silently discarding the user's
        // deliberate edit even though is_scratch ends up correctly false
        // (caught in review, not from a live report).
        return SaveSearchAction::withUserLock($savedSearch->user_id, function () use ($savedSearch, $name, $normalized, $fingerprint) {
            return $this->save($savedSearch, $name, $normalized, $fingerprint);
        });
    }

    private function save(SavedSearch $savedSearch, string $name, array $normalized, string $fingerprint): SavedSearch
    {
        $duplicate = SavedSearch::where('user_id', $savedSearch->user_id)
            ->where('fingerprint', $fingerprint)
            ->where('id', '!=', $savedSearch->id)
            ->first();

        if ($duplicate) {
            throw new DuplicateSavedSearchException($duplicate);
        }

        // A deliberate edit is a "keep this" action — clears is_scratch so
        // the next ordinary nav search (SaveSearchAction) can't silently
        // overwrite it via the rotating scratch-slot behavior. Deliberately
        // does NOT also pin the row (that used to be the only protection
        // mechanism, which meant an edit-without-pinning still got quietly
        // pinned, and unpinning it afterward threw it right back into the
        // overwrite pool — see the is_scratch column's migration). Pinning
        // is purely a display choice now, left exactly as the caller had it.
        //
        // Resetting last_checked_at when the criteria itself changed (not
        // just the name) and notifications are on: without this, editing an
        // enabled search from e.g. "Los Angeles" to "all remote events"
        // could immediately email a backlog of every remote event published
        // since the OLD (Los Angeles) search's last check, which is a
        // completely different, unrelated window of time — see
        // NotifySavedSearchMatchesCommand's own docblock for why "new" means
        // "newly published since last checked", and this is what keeps that
        // promise meaning "since these exact criteria started being
        // watched."
        $criteriaChanged = $fingerprint !== $savedSearch->fingerprint;

        try {
            $savedSearch->update([
                'name' => $name,
                'criteria' => $normalized,
                'fingerprint' => $fingerprint,
                'is_scratch' => false,
                'last_checked_at' => ($criteriaChanged && $savedSearch->notify_new_events) ? now() : $savedSearch->last_checked_at,
            ]);
        } catch (QueryException $e) {
            // Backstop for the check-then-write gap above: two concurrent
            // edits (double-click, two tabs) of the same row into the same
            // new criteria could both pass the pre-check and only one wins
            // the (user_id, fingerprint) unique constraint. Re-resolve the
            // real duplicate rather than letting a raw 500 escape; anything
            // else is a genuine, unexpected DB error and should still throw.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $duplicate = SavedSearch::where('user_id', $savedSearch->user_id)
                ->where('fingerprint', $fingerprint)
                ->where('id', '!=', $savedSearch->id)
                ->first();

            throw $duplicate ? new DuplicateSavedSearchException($duplicate) : $e;
        }

        return $savedSearch;
    }
}
