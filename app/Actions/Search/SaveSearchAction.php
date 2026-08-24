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
 * migration), fingerprints it, and writes it to the user's SINGLE scratch
 * "current search" slot — overwriting whatever was there before, not
 * adding a new row. Casual, undirected browsing (search NY, then LA, then
 * SF, never touching the editor) should never pile up rows on the Saved
 * Search Preferences page — only what the user deliberately keeps should
 * show up there (spelled out directly by the user, with a worked example,
 * after an earlier multi-row-history version of this class did exactly the
 * piling-up it shouldn't).
 * A row stops being reclaimable the moment a person deliberately touches
 * it: pinning it (SavedSearchController::togglePin) or editing-and-saving
 * it (UpdateSavedSearchAction) both clear `is_scratch` permanently (see
 * that column's migration — pinned alone isn't enough, since unpinning a
 * deliberately-edited row must not throw it back into this reclaim pool).
 * From that point on it's a real, kept search, not the rotating slot —
 * the next ordinary search creates a fresh scratch row instead of touching
 * it. MAX_SAVED_SEARCHES caps how many rows can exist in total (protected
 * rows plus the one scratch slot), not how many casual searches get
 * tracked — undirected browsing only ever occupies that one slot.
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
     * Null return means "at the cap and every row is pinned/protected" —
     * no scratch row left to reuse, and this criteria doesn't match any
     * existing row.
     */
    public function handle(int $userId, string $name, array $criteria): ?SavedSearch
    {
        return static::withUserLock($userId, fn () => $this->save($userId, $name, $criteria));
    }

    /**
     * Per-user lock around a read-decide-write sequence on this user's saved
     * searches — shared with every OTHER write path that touches a row this
     * class's eviction might also be touching concurrently
     * (SavedSearchController::togglePin/destroy, UpdateSavedSearchAction),
     * not just handle() above. Without this, e.g. pinning a row and an
     * unrelated auto-save evicting that exact row could interleave: pin
     * commits is_scratch=false, but the auto-save's SELECT (for "oldest
     * scratch row") ran before that commit and still matches it, so its own
     * update() — touching disjoint columns (name/criteria/fingerprint) —
     * lands anyway, leaving a row that LOOKS fully protected (pinned=true,
     * is_scratch=false) but silently holds unrelated content (caught in
     * review, not from a live report).
     *
     * Waits up to 3s for the lock rather than failing fast — these writes
     * are infrequent enough that real contention is rare, and losing one
     * outright (vs. a few ms of queueing) is worse UX.
     */
    public static function withUserLock(int $userId, \Closure $callback)
    {
        return Cache::lock("save-search:{$userId}", 5)->block(3, $callback);
    }

    private function save(int $userId, string $name, array $criteria): ?SavedSearch
    {
        $normalized = $this->normalizeCriteria->handle($criteria);
        $fingerprint = hash('sha256', json_encode($normalized));

        // This search may already exist as one of the user's rows —
        // scratch or protected (e.g. they pinned "Broadway NYC" and then
        // just hit Search again with the same filters). `(user_id,
        // fingerprint)` is a DB unique constraint, so writing a duplicate
        // fingerprint below would throw; recognizing the match up front
        // avoids that. Protected rows are never touched by this passive
        // auto-save (see class docblock), only the recency of an
        // exact-match scratch row is bumped.
        $existing = SavedSearch::where('user_id', $userId)->where('fingerprint', $fingerprint)->first();

        if ($existing) {
            if ($existing->is_scratch) {
                // Self-heal here too, not just in the reuse branch below —
                // otherwise repeating a search that happens to match one of
                // several stray scratch rows would return early and leave
                // the others stranded indefinitely instead of consolidating
                // on this user's very next ordinary search (caught in
                // review).
                $this->deleteOtherScratchRows($userId, $existing->id);
                $existing->touch();
            }

            return $existing;
        }

        // Reuse the user's single current scratch row, if one exists —
        // this IS the "casual browsing" tracking: whatever was searched
        // last gets overwritten in place, never accumulating. A protected
        // row (pinned or deliberately edited-and-saved) is never a
        // candidate here, so it's never at risk of this overwrite.
        // ->latest('updated_at'), not an unordered first() — this also
        // self-heals: any OTHER stray is_scratch row for this user (there
        // should only ever be one; a prior version of this class briefly
        // let several accumulate before this revert) gets deleted below the
        // moment this user's very next ordinary search runs, with no manual
        // cleanup needed anywhere this touches.
        $current = SavedSearch::where('user_id', $userId)->where('is_scratch', true)
            ->latest('updated_at')->first();

        if ($current) {
            $this->deleteOtherScratchRows($userId, $current->id);

            $current->update([
                'name' => $name,
                'criteria' => $normalized,
                'fingerprint' => $fingerprint,
            ]);

            return $current;
        }

        // Only reachable once every existing row is protected (no scratch
        // slot above to reuse) AND this criteria doesn't match any existing
        // fingerprint — i.e. this specific call would actually grow the row
        // count. Checked here, not as an upfront count() gate in the
        // controller, so a re-search that matches an existing/protected row
        // (or that would just overwrite the scratch slot) still succeeds
        // even when the user is already at the cap.
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
                'is_scratch' => true,
            ]);
        } catch (QueryException $e) {
            return $this->resolveDuplicateInsertRace($e, $userId, $fingerprint);
        }
    }

    private function deleteOtherScratchRows(int $userId, int $keepId): void
    {
        SavedSearch::where('user_id', $userId)->where('is_scratch', true)
            ->where('id', '!=', $keepId)->delete();
    }

    private function resolveDuplicateInsertRace(QueryException $e, int $userId, string $fingerprint): SavedSearch
    {
        // The per-user lock in handle() already serializes same-user
        // auto-saves, so this is defense-in-depth rather than the primary
        // safeguard now — it only matters if a lock is ever acquired
        // without actually excluding a concurrent writer (e.g.
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
