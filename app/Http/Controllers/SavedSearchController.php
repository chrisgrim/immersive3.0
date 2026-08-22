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
     * the Hub's "Saved Search Preferences" tab.
     */
    public function index(Request $request, BuildSearchUrlAction $buildUrl)
    {
        $searches = $request->user()->savedSearches()
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SavedSearch $search) => $this->mapSearch($search, $buildUrl));

        return response()->json(['searches' => $searches]);
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

        $savedSearch->delete();

        return response()->json(['message' => 'Saved search removed']);
    }

    /**
     * Toggles pinned on/off — no request body needed. Pinning protects this
     * row from SaveSearchAction's overwrite-in-place behavior; the next
     * search the user does creates a fresh unpinned row instead.
     */
    public function togglePin(Request $request, SavedSearch $savedSearch, BuildSearchUrlAction $buildUrl)
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 404);

        $nowPinned = ! $savedSearch->pinned;

        if (! $nowPinned) {
            // Unpinning makes this row the user's one "recent search" slot
            // again — SaveSearchAction only ever reclaims the SINGLE most
            // recent unpinned row on the next search, so if an unpinned row
            // already existed (the actual current slot), leaving both around
            // would silently orphan whichever one the next auto-save doesn't
            // pick. Clear that out here instead, at the moment a second
            // unpinned row would otherwise be created.
            SavedSearch::where('user_id', $savedSearch->user_id)
                ->where('pinned', false)
                ->where('id', '!=', $savedSearch->id)
                ->delete();
        }

        $savedSearch->update(['pinned' => $nowPinned]);

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
            'created_at' => $search->created_at,
            // The overwrite-in-place slot's own criteria/name get replaced
            // on every search (see SaveSearchAction), so created_at can be
            // stale — updated_at is what actually reflects "when was this
            // last searched", which is what the UI shows.
            'updated_at' => $search->updated_at,
        ];
    }
}
