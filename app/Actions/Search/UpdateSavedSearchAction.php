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

        $duplicate = SavedSearch::where('user_id', $savedSearch->user_id)
            ->where('fingerprint', $fingerprint)
            ->where('id', '!=', $savedSearch->id)
            ->first();

        if ($duplicate) {
            throw new DuplicateSavedSearchException($duplicate);
        }

        // A deliberate edit is a "keep this" action — pins the row so the
        // next ordinary nav search (SaveSearchAction) can't silently
        // overwrite it via the rotating unpinned-slot behavior.
        try {
            $savedSearch->update([
                'name' => $name,
                'criteria' => $normalized,
                'fingerprint' => $fingerprint,
                'pinned' => true,
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
