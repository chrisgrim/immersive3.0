<?php

namespace App\Actions\Search;

use App\Models\SavedSearch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Auto-save, not "create a saved search" — this is what runs every time a
 * user submits a search on the Location/At Home tabs (see nav-search.vue's
 * handleLocationSearch/handleAtHomeSearch). It normalizes the criteria into
 * the canonical shape SavedSearch rows store (see the saved_searches
 * migration), fingerprints it, and writes it to the user's single unpinned
 * "recent search" slot — overwriting whatever was there before, not adding
 * a new row, so a user doing dozens of searches a day never accumulates
 * more than one unpinned row plus however many they've deliberately pinned.
 * Pinning (SavedSearchController::togglePin) is what protects a row from
 * this overwrite; the next call after a pin finds no unpinned row and
 * creates a fresh one instead of touching the pinned one.
 */
class SaveSearchAction
{
    /**
     * Matches the dropdown's fixed 6 slots (SavedSearchesEdit's picker) —
     * not an arbitrary backstop number, an actual UI capacity limit.
     */
    public const MAX_SAVED_SEARCHES = 6;

    public function __construct(private NormalizeSavedSearchCriteriaAction $normalizeCriteria) {}

    /**
     * Null return means "at the cap and this would have created a new row"
     * — see the cap check below for why that's only checked this late, not
     * as an upfront count() gate in the caller.
     */
    public function handle(int $userId, string $name, array $criteria): ?SavedSearch
    {
        // Per-user lock around the whole read-decide-write sequence below —
        // two concurrent auto-saves for the same user (e.g. two open tabs)
        // used to be able to both read "5 rows, under the cap" before either
        // inserted, letting the count briefly exceed MAX_SAVED_SEARCHES with
        // no path back down (the extra row is never cleaned up, and once
        // over the cap even legitimate future saves start getting rejected).
        // Waits up to 3s for the lock rather than failing fast — auto-saves
        // are infrequent enough that real contention is rare, and losing an
        // auto-save outright (vs. a few ms of queueing) is worse UX.
        return Cache::lock("save-search:{$userId}", 5)->block(3, fn () => $this->save($userId, $name, $criteria));
    }

    private function save(int $userId, string $name, array $criteria): ?SavedSearch
    {
        $normalized = $this->normalizeCriteria->handle($criteria);
        $fingerprint = hash('sha256', json_encode($normalized));

        // This search may already exist as one of the user's rows — pinned
        // or not (e.g. they pinned "Broadway NYC" and then just hit Search
        // again with the same filters). `(user_id, fingerprint)` is a DB
        // unique constraint, so writing a duplicate fingerprint below would
        // throw; recognizing the match up front avoids that. Pinned rows
        // are never touched by this passive auto-save (pinning protects a
        // row — see class docblock), only the recency of an exact-match
        // unpinned row is bumped.
        $existing = SavedSearch::where('user_id', $userId)->where('fingerprint', $fingerprint)->first();

        if ($existing) {
            if (! $existing->pinned) {
                $existing->touch();
            }

            return $existing;
        }

        $current = SavedSearch::where('user_id', $userId)->where('pinned', false)->latest()->first();

        if ($current) {
            $current->update([
                'name' => $name,
                'criteria' => $normalized,
                'fingerprint' => $fingerprint,
            ]);

            return $current;
        }

        // Only reachable once every existing row is pinned (no unpinned slot
        // above to reuse) AND this criteria doesn't match any existing
        // fingerprint — i.e. this specific call would actually grow the row
        // count. Checked here, not as an upfront count() gate in the
        // controller, so a re-search that matches an existing/pinned row (or
        // that would just overwrite the unpinned slot) still succeeds even
        // when the user is already at the cap — neither of those paths above
        // adds a row, so neither should be blocked by a row-count limit.
        if (SavedSearch::where('user_id', $userId)->count() >= self::MAX_SAVED_SEARCHES) {
            return null;
        }

        try {
            return SavedSearch::create([
                'user_id' => $userId,
                'name' => $name,
                'criteria' => $normalized,
                'fingerprint' => $fingerprint,
                'pinned' => false,
            ]);
        } catch (QueryException $e) {
            // The per-user lock in handle() already serializes same-user
            // auto-saves, so this is defense-in-depth rather than the
            // primary safeguard now — it only matters if a lock is ever
            // acquired without actually excluding a concurrent writer (e.g.
            // Cache::lock()'s TTL expiring mid-request under extreme load).
            // Same fallback pattern as RecordLoginHistory/
            // PersonalInformationController::updateLocation() for the same
            // class of race: fetch the winner's row instead of throwing.
            if (! str_contains($e->getMessage(), '1062') && ! str_contains(strtolower($e->getMessage()), 'unique')) {
                throw $e;
            }

            return SavedSearch::where('user_id', $userId)->where('fingerprint', $fingerprint)->firstOrFail();
        }
    }
}
