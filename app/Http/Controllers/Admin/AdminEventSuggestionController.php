<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventSuggestion;

class AdminEventSuggestionController extends Controller
{
    public function index()
    {
        // Pending and done are read separately so a long history of done ones can
        // never push a new suggestion off the page. Each is capped so a flood
        // does not load every row at once.
        $pending = EventSuggestion::with('user:id,name,email')
            ->where('status', 'pending')
            ->latest()
            ->limit(200)
            ->get();

        $done = EventSuggestion::with(['user:id,name,email', 'processor:id,name'])
            ->where('status', 'done')
            ->latest()
            ->limit(200)
            ->get();

        // One list, newest first, so the page reads as a record of everything sent.
        $suggestions = $pending->concat($done)
            ->sortBy([['created_at', 'desc'], ['id', 'desc']])
            ->values();

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
