<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ReviewEvent;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    public function index(Request $request)
    {
        return ReviewEvent::with(['event', 'user'])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('event', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20);
    }

    public function update(ReviewEvent $review, Request $request)
    {
        $validated = $request->validate([
            'reviewer_name' => 'required|string',
            'url' => 'required|url',
            'review' => 'required|string',
            'rank' => 'required|integer|min:1|max:5',
        ]);

        $review->update($validated);
        return $review->fresh(['event', 'user']);
    }

    public function destroy(ReviewEvent $review)
    {
        $review->delete();
        return response()->noContent();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event.id' => 'required|exists:events,id',
            'reviewername' => 'required|string',
            'url' => 'required|url',
            'review' => 'required|string',
            'rank' => 'required|integer|between:1,5'
        ]);

        $review = ReviewEvent::create([
            'event_id' => $request->event['id'],
            'reviewer_name' => $request->reviewername,
            'url' => $request->url,
            'review' => $request->review,
            'rank' => $request->rank,
            'user_id' => auth()->id(),
            'organizer_id' => $request->event['organizer_id'] ?? null
        ]);
        
        return $review->fresh(['event', 'user']);
    }
} 