<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventSuggestion;
use Illuminate\Http\Request;

class AdminEventSuggestionController extends Controller
{
    public const PER_PAGE = 25;

    /**
     * Newest first, a page at a time. With ?done=1 (the page's default) the done
     * ones are mixed in as a record; without it only the pending queue shows.
     */
    public function index(Request $request)
    {
        $page = EventSuggestion::with(['user:id,name,email', 'processor:id,name'])
            ->whereIn('status', $request->boolean('done') ? ['pending', 'done'] : ['pending'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        $suggestions = $page->getCollection();

        return response()->json([
            'suggestions' => $suggestions->map(fn (EventSuggestion $s) => [
                'id' => $s->id,
                'message' => $s->message,
                'status' => $s->status,
                'created_at' => $s->created_at,
                'processed_at' => $s->processed_at,
                'processed_by' => $s->processor?->name,
                'user' => $s->user ? [
                    'id' => $s->user->id,
                    'name' => $s->user->name,
                    'email' => $s->user->email,
                ] : null,
            ]),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** Handled (added to the site, or looked at and passed on): off the queue, kept for the record. */
    public function done(EventSuggestion $suggestion)
    {
        $suggestion->status = 'done';
        $suggestion->processed_by = auth()->id();
        $suggestion->processed_at = now();
        $suggestion->save();

        return response()->json(['message' => 'Marked as done']);
    }

    /** Spam or junk: gone for good. */
    public function destroy(EventSuggestion $suggestion)
    {
        $suggestion->delete();

        return response()->json(['message' => 'Suggestion deleted']);
    }
}
