<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventSuggestion;

class AdminEventSuggestionController extends Controller
{
    public function index()
    {
        $suggestions = EventSuggestion::with('user:id,name,email')
            ->where('status', 'pending')
            ->latest()
            // A flood would otherwise load every row into the page at once.
            ->limit(200)
            ->get();

        return response()->json([
            'suggestions' => $suggestions->map(fn (EventSuggestion $s) => [
                'id' => $s->id,
                'message' => $s->message,
                'created_at' => $s->created_at,
                'user' => $s->user ? [
                    'id' => $s->user->id,
                    'name' => $s->user->name,
                    'email' => $s->user->email,
                ] : null,
            ]),
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
