<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Projects\MergeVenues;
use App\Actions\Projects\UpdateVenue;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Messaging\Message;
use App\Models\Venue;
use App\Support\PhotoGallery;
use Illuminate\Http\Request;

class AdminVenueController extends Controller
{
    public function index(Request $request)
    {
        return Venue::query()
            ->whereNull('merged_into_id')
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->keywords, fn ($query, $keywords) => $query->where('name', 'like', '%'.addcslashes($keywords, '\\%_').'%'))
            ->orderBy('name')
            ->paginate(20);
    }

    /** A new name moves the address, and the model keeps the old one as a redirect. */
    public function update(Request $request, Venue $venue, UpdateVenue $update)
    {
        $this->authorize('update', $venue);

        return response()->json(['venue' => $update->handle($venue, $request->validate(UpdateVenue::RULES, UpdateVenue::MESSAGES), $request->user())]);
    }

    /** The venue page's picture, replacing the one before. */
    public function image(Request $request, Venue $venue, UpdateVenue $update)
    {
        $this->authorize('update', $venue);

        $request->validate(UpdateVenue::IMAGE_RULES);

        return response()->json(['venue' => $update->image($venue, $request->file('image'), $request->user())]);
    }

    /** One venue for the review screen: what an organizer review shows, and who added it. */
    public function show(Venue $venue)
    {
        $venue->load(['owner.owner', 'ownerUser']);

        return response()->json([
            ...$venue->toArray(),
            'photos' => PhotoGallery::present($venue),
            'added_by' => $venue->owner ? [
                'organizer' => $venue->owner->only(['name', 'slug']),
                'user' => $venue->owner->owner?->only(['name', 'email']),
            ] : null,
            'claimed_by' => $venue->ownerUser?->only(['name', 'email']),
        ]);
    }

    /**
     * "Ask a question", as on an organizer review: the question goes to the inbox
     * of whoever runs the team that added the venue. The venue stays waiting.
     */
    public function question(Request $request, Venue $venue)
    {
        $validated = $request->validate(['question' => ['required', 'string', 'max:1000']]);

        $team = $venue->owner;
        abort_unless($team && $team->user_id, 422, 'Nobody to ask: no team added this venue.');

        Message::notification($team, 'About the venue '.$venue->name.': '.$validated['question'], $team->slug);
        AuditLog::record($venue, 'venue.question', $request->user());

        return response()->json(['message' => 'Question sent.']);
    }

    public function approve(Request $request, Venue $venue)
    {
        $this->authorize('approve', $venue);

        $venue->update(['status' => Venue::APPROVED]);

        AuditLog::record($venue, 'venue.approved', $request->user());

        return response()->json(['venue' => $venue]);
    }

    /** {venue} is the duplicate: it folds into `into_venue_id`. */
    public function merge(Request $request, Venue $venue, MergeVenues $merge)
    {
        $validated = $request->validate(['into_venue_id' => ['required', 'integer', 'exists:venues,id']]);

        return response()->json(['venue' => $merge->handle($venue, Venue::findOrFail($validated['into_venue_id']), $request->user())]);
    }
}
