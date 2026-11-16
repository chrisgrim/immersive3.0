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
            ->when($request->status, fn ($query, $status) => $query->where('status', $status), fn ($query) => $query->where('status', '!=', Venue::DRAFT))
            ->when($request->keywords, fn ($query, $keywords) => $query->where('name', 'like', '%'.addcslashes($keywords, '\\%_').'%'))
            ->orderBy('name')
            ->paginate(20);
    }

    /** A new name moves the address, and the model keeps the old one as a redirect. */
    public function update(Request $request, Venue $venue, UpdateVenue $update)
    {
        $this->authorize('update', $venue);

        UpdateVenue::prepare($request);

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

        $text = 'About the venue '.$venue->name.': '.$validated['question'];
        // Owned by a person (an approved claim, or added by someone with no
        // organization): ask them. Otherwise whoever runs the team that added it.
        if ($venue->user_id) {
            Message::notification($venue, $text, $venue->slug);
        } else {
            $team = $venue->owner;
            abort_unless($team && $team->user_id, 422, 'Nobody to ask: nobody owns or added this venue.');
            Message::notification($team, $text, $team->slug);
        }
        AuditLog::record($venue, 'venue.question', $request->user());

        return response()->json(['message' => 'Question sent.']);
    }

    /**
     * Remove a venue that was sent in but should not be on EI (junk, a test,
     * not a real place). Only one still waiting: a live venue is merged into
     * the right one instead. Events that picked it keep their address as a
     * plain one, and whoever added it is told why.
     */
    public function remove(Request $request, Venue $venue)
    {
        $this->authorize('approve', $venue);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $team = $venue->owner;
        $name = $venue->name;

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $venue, $validated) {
            // Checked under a lock on the row: an approval or merge crossing
            // this one wins, and a live venue is never removed.
            $locked = Venue::whereKey($venue->id)->lockForUpdate()->first();
            abort_unless($locked?->status === Venue::PENDING && $locked->merged_into_id === null, 422, 'Only a venue waiting for approval can be removed. Merge a live one into the right venue instead.');
            abort_if(Venue::where('merged_into_id', $venue->id)->exists(), 422, 'Other venues were merged into this one. Merge it into the right venue instead.');
            // Back to a plain address (kind NULL), as ApplyVenue leaves one.
            $venue->locations()->update(['venue_id' => null, 'kind' => null]);
            $venue->nameChangeRequests()->where('status', 'pending')->update(['status' => 'withdrawn']);
            AuditLog::record($venue, 'venue.removed', $request->user(), ['reason' => $validated['reason']]);
            // Picture files last, once nothing below can roll the delete back.
            foreach ($venue->images as $image) {
                try {
                    \App\Services\ImageHandler::deleteImage($image);
                } catch (\Throwable $e) {
                    report($e);
                    $image->delete();
                }
            }
            $venue->delete();
        });

        $text = 'The venue '.$name.' was not added to Everything Immersive: '.$validated['reason'];
        // Its owner (a person, from a claim or added with no organization), else the team that added it.
        // (The thread is on the venue's row as it was: as "Ask a question" does.)
        if ($venue->user_id) {
            Message::notification($venue, $text, $venue->slug);
        } elseif ($team && $team->user_id) {
            Message::notification($team, $text, $team->slug);
        }

        return response()->json(['message' => 'Venue removed.']);
    }

    public function approve(Request $request, Venue $venue)
    {
        $this->authorize('approve', $venue);
        // A draft is still being added by its owner (it may have no address
        // yet). Under the same lock as Remove, so the two never cross.
        \Illuminate\Support\Facades\DB::transaction(function () use ($venue) {
            $locked = Venue::whereKey($venue->id)->lockForUpdate()->first();
            abort_if(! $locked, 404);
            abort_if($locked->status === Venue::DRAFT, 422, 'This venue is still a draft: its owner has not sent it yet.');
            $locked->update(['status' => Venue::APPROVED]);
        });
        $venue->refresh();

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
