<?php

namespace App\Http\Controllers;

use App\Actions\Search\BuildSearchUrlAction;
use App\Actions\Search\SaveSearchAction;
use App\Actions\Search\UpdateSavedSearchAction;
use App\Http\Requests\StoreSavedSearchRequest;
use App\Http\Requests\UpdateSavedSearchRequest;
use App\Models\SavedSearch;
use Illuminate\Http\Request;

class SavedSearchController extends Controller
{
    /**
     * The current user's saved searches, most-recently-saved first. Backs
     * both the Hub's "Saved Search Preferences" tab (full list, up to
     * MAX_SAVED_SEARCHES) and the nav search bar's "Recent searches"
     * dropdown (?dropdown=1 — a reduced view, see limitToDropdown() below).
     *
     * Ordered by updated_at, not created_at (caught in review) —
     * SaveSearchAction's eviction overwrites a scratch row's name/criteria
     * in place and bumps its updated_at, but never touches created_at
     * (which can be from whenever that row was first created, potentially
     * the oldest of all of them). Sorting by created_at would bury a
     * just-performed search under older-content rows purely because their
     * row happened to be created more recently — mapSearch()'s own comment
     * already documents created_at as unreliable for "how recent is this"
     * for exactly this reason.
     */
    public function index(Request $request, BuildSearchUrlAction $buildUrl)
    {
        $searches = $request->user()->savedSearches()
            ->orderByDesc('pinned')
            ->orderByDesc('updated_at')
            ->get();

        if ($request->boolean('dropdown')) {
            $searches = $this->limitToDropdown($searches);
        }

        return response()->json(['searches' => $searches->map(fn (SavedSearch $search) => $this->mapSearch($search, $buildUrl))->values()]);
    }

    /**
     * The nav dropdown's own display rule (spelled out directly by the
     * user, not inferred): show every PINNED search in full — those are
     * the ones someone deliberately chose to keep visible — plus at most
     * ONE more, the single most-recently-touched search that isn't pinned.
     * Not "every unpinned/protected search" — a user with several distinct
     * saved-but-unpinned searches (is_scratch=false rows from editing, or
     * genuinely scratch rows) would otherwise see the dropdown fill up with
     * all of them, which is exactly the clutter this exists to avoid. If
     * the most-recently-touched search overall already IS pinned, nothing
     * extra is added — it's already in $pinned, not duplicated.
     * $searches is already sorted pinned-desc, updated_at-desc from the
     * query above, so ->first() on the unpinned subset is already the most
     * recent one — no extra sort needed.
     */
    private function limitToDropdown(\Illuminate\Support\Collection $searches): \Illuminate\Support\Collection
    {
        $pinned = $searches->where('pinned', true)->values();
        $mostRecentUnpinned = $searches->where('pinned', false)->first();

        return $mostRecentUnpinned ? $pinned->push($mostRecentUnpinned) : $pinned;
    }

    /**
     * Idempotent — saving criteria that fingerprint the same as an existing
     * saved search returns that row rather than creating a duplicate, and
     * reusing the single unpinned "recent search" slot doesn't grow the row
     * count either (see SaveSearchAction). Capped at
     * SaveSearchAction::MAX_SAVED_SEARCHES: not a backstop number, the
     * dropdown that lists these only has that many slots. The cap is
     * enforced inside the action itself (returns null instead of creating a
     * row past the limit), not as an upfront count() check here — an
     * upfront check would 422 even a re-search that wouldn't have added a
     * row at all (an exact match, or one that just overwrites the unpinned
     * slot), which is wrong regardless of how many rows already exist.
     */
    public function store(StoreSavedSearchRequest $request, SaveSearchAction $action, BuildSearchUrlAction $buildUrl)
    {
        $user = $request->user();

        $search = $action->handle($user->id, $request->input('name'), $request->input('criteria'));

        if (! $search) {
            return response()->json(['message' => 'You have saved the maximum of '.SaveSearchAction::MAX_SAVED_SEARCHES.' searches — remove one before saving another.'], 422);
        }

        return response()->json(['search' => $this->mapSearch($search, $buildUrl)], 201);
    }

    /**
     * Deliberate edit of one exact saved search from the Hub's editor — not
     * the same thing as `store()`'s passive auto-save (see
     * UpdateSavedSearchAction for why it can't reuse SaveSearchAction).
     * Always pins the row (see UpdateSavedSearchAction) and always returns
     * the full mapped search, including a freshly-built replay `url`.
     */
    public function update(UpdateSavedSearchRequest $request, SavedSearch $savedSearch, UpdateSavedSearchAction $action, BuildSearchUrlAction $buildUrl)
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 404);

        $updated = $action->handle($savedSearch, $request->input('name'), $request->input('criteria'));

        return response()->json(['search' => $this->mapSearch($updated, $buildUrl)]);
    }

    public function destroy(Request $request, SavedSearch $savedSearch)
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 404);

        // Same per-user lock as every other write below — without it, a
        // concurrent SaveSearchAction eviction that already selected this
        // exact row as "oldest scratch" could still land its update() after
        // this delete (Eloquent's update() doesn't check affected-row count),
        // leaving a stale in-memory row that looks saved but doesn't exist
        // (caught in review).
        SaveSearchAction::withUserLock($savedSearch->user_id, fn () => $savedSearch->delete());

        return response()->json(['message' => 'Saved search removed']);
    }

    /**
     * Toggles pinned on/off — no request body needed. Pinning is purely a
     * display choice (shown at the top of the list); the actual protection
     * from SaveSearchAction's overwrite-in-place behavior is `is_scratch`,
     * which pinning also clears (see SavedSearch::booted()) but unpinning
     * never restores — a search the user pinned (or edited) must stay safe
     * from the auto-save's eviction pool even after being unpinned.
     * Unpinning otherwise has no side effects on the user's other rows,
     * and deliberately doesn't try to tidy them: SaveSearchAction keeps
     * exactly ONE scratch row per user (the rotating "current search"
     * slot), and its own deleteOtherScratchRows() consolidates any strays
     * on that user's very next ordinary search. Cleaning up from here would
     * duplicate that self-healing outside the per-user lock that makes it
     * safe.
     *
     * (This paragraph previously claimed the opposite — that a multi-row
     * recent-searches history was the intended state. That was true only of
     * a short-lived version of SaveSearchAction, reverted in 79ec466; the
     * comment wasn't reverted with it. Left recorded here because the
     * multi-row design is a specific thing not to reintroduce, not just an
     * outdated detail.)
     *
     * Locked the same as SaveSearchAction's own read-decide-write sequence
     * (SaveSearchAction::withUserLock) — pinning happens between reading
     * this row and committing is_scratch=false, and without excluding a
     * concurrent auto-save eviction from that same window, its SELECT could
     * still match this row as "oldest scratch" and overwrite its
     * name/criteria/fingerprint right after the pin commits, leaving a row
     * that looks fully protected but silently holds unrelated content
     * (caught in review, not from a live report).
     */
    public function togglePin(Request $request, SavedSearch $savedSearch, BuildSearchUrlAction $buildUrl)
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 404);

        SaveSearchAction::withUserLock($savedSearch->user_id, function () use ($savedSearch) {
            $savedSearch->pinned = ! $savedSearch->pinned;
            $savedSearch->save();
        });

        return response()->json(['search' => $this->mapSearch($savedSearch, $buildUrl)]);
    }

    /**
     * The saved-search "notify me about new events" toggle — see
     * NotifySavedSearchMatchesCommand's own docblock for the full design.
     * Enabling is restricted server-side to moderators/admins
     * (User::isModerator()) — same gate as the API-keys/MCP-tokens page; the
     * frontend also hides the control for anyone else, but that's a UX
     * nicety, not the enforcement — see this method for the real gate.
     * Disabling is never restricted: turning something off should never be
     * blocked, even for a row that somehow ended up enabled outside that
     * group (e.g. a user was demoted after enabling it).
     */
    public function toggleNotify(Request $request, SavedSearch $savedSearch, BuildSearchUrlAction $buildUrl)
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 404);

        $nowEnabled = ! $savedSearch->notify_new_events;

        if ($nowEnabled && ! $request->user()->isModerator()) {
            abort(403, 'This feature is not yet available for your account.');
        }

        // Same per-user lock as togglePin/destroy — enabling clears
        // is_scratch below, and without excluding a concurrent auto-save
        // eviction from that same window, it could still repurpose this
        // exact row into unrelated content right after this commits (same
        // class of race as togglePin's own comment).
        SaveSearchAction::withUserLock($savedSearch->user_id, fn () => $savedSearch->update([
            'notify_new_events' => $nowEnabled,
            // Reset on enabling — otherwise the next scheduled check would
            // treat "never checked" as "check everything published since
            // this row's very first save", potentially emailing a backlog
            // of old events on the very first run. Left alone on disabling —
            // harmless, and preserves the "how far did we get" cursor in
            // case it's re-enabled later without touching the criteria.
            'last_checked_at' => $nowEnabled ? now() : $savedSearch->last_checked_at,
            // Enabling notify is as deliberate an action as pinning or
            // editing (Codex caught this in review) — without clearing
            // is_scratch here, a row enabled straight from an auto-saved
            // "recent search" stays evictable, and SaveSearchAction could
            // later silently repurpose it into a DIFFERENT search while
            // this row's notify_new_events/last_checked_at stay set,
            // quietly emailing the user about a search they never opted
            // into. Left alone on disabling, same as pinned/is_scratch.
            'is_scratch' => $nowEnabled ? false : $savedSearch->is_scratch,
        ]));

        return response()->json(['search' => $this->mapSearch($savedSearch, $buildUrl)]);
    }

    private function mapSearch(SavedSearch $search, BuildSearchUrlAction $buildUrl): array
    {
        return [
            'id' => $search->id,
            'name' => $search->name,
            'criteria' => $search->criteria,
            'url' => $buildUrl->handle($search->criteria),
            'pinned' => $search->pinned,
            'notifyNewEvents' => $search->notify_new_events,
            'created_at' => $search->created_at,
            // The overwrite-in-place slot's own criteria/name get replaced
            // on every search (see SaveSearchAction), so created_at can be
            // stale — updated_at is what actually reflects "when was this
            // last searched", which is what the UI shows.
            'updated_at' => $search->updated_at,
        ];
    }
}
