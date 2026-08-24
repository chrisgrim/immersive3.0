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
 * migration), fingerprints it, and writes a distinct "recent search" row
 * for each genuinely different search — this is meant to be a real, if
 * short, history (the dropdown is literally labeled "Recent searches",
 * plural), not a single slot that only ever shows the latest one. Bounded
 * by MAX_SAVED_SEARCHES: once a user is at the cap, the least-recently-
 * touched scratch row is evicted (its name/criteria overwritten in place)
 * to make room, rather than growing the row count further.
 * A row stops being evictable the moment a person deliberately touches it:
 * pinning it (SavedSearchController::togglePin) or editing it
 * (UpdateSavedSearchAction) both clear `is_scratch` permanently (see that
 * column's migration — pinned alone isn't enough, since unpinning a
 * deliberately-edited row must not throw it back into this eviction pool).
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
     * no scratch row left to evict, and this criteria doesn't match any
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
                $existing->touch();
            }

            return $existing;
        }

        // Under the cap: every genuinely new search gets its own row, so the
        // "Recent searches" dropdown actually shows a short history instead
        // of collapsing to whatever was searched last.
        if (SavedSearch::where('user_id', $userId)->count() < self::MAX_SAVED_SEARCHES) {
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

        // At the cap — evict the least-recently-touched scratch row (true
        // LRU: the one that's gone longest without being re-searched or
        // re-matched) rather than growing past the dropdown's fixed slots.
        // Only reachable once this criteria doesn't match any existing
        // fingerprint (the check above already returned) — checked here,
        // not as an upfront gate in the controller, so a re-search that
        // matches an existing row still succeeds even at the cap. Tiebreak
        // on id: `updated_at` has only second precision, so several rows
        // created/touched within the same second would otherwise leave the
        // real oldest-first order to the DB's whim.
        $oldest = SavedSearch::where('user_id', $userId)->where('is_scratch', true)
            ->oldest('updated_at')->oldest('id')->first();

        if (! $oldest) {
            // Every row is pinned/protected — genuinely no room left.
            return null;
        }

        $oldest->update([
            'name' => $name,
            'criteria' => $normalized,
            'fingerprint' => $fingerprint,
        ]);

        return $oldest;
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
