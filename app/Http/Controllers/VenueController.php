<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\SlugRedirect;
use App\Models\Venue;
use App\Models\VenueClaim;
use App\Support\Projects\InstanceAddress;
use App\Support\Projects\RunRow;

class VenueController extends Controller
{
    /**
     * By hand, as events are: a live venue by slug, a merged one to its
     * winner, then an old address. A venue nobody has approved is a 404. Only
     * published, non-hidden, non-deleted runs are listed: a secret-location
     * listing never appears here, however it was edited.
     */
    public function show(string $venue)
    {
        $found = Venue::where('slug', $venue)->first();

        if ($found?->merged_into_id !== null && $found !== null) {
            $found = $found->winner();

            abort_unless($found->merged_into_id === null, 404);

            return redirect($found->publicPath(), 301);
        }

        if ($found === null) {
            $destination = SlugRedirect::where('path', 'venues/'.$venue)->first()?->destination();

            abort_unless($destination !== null, 404);

            return redirect(...$destination);
        }

        // Not approved yet (waiting for EI, or a draft): only its keeper and
        // staff see it, as a preview; to everyone else it does not exist.
        // A signed link from Add a venue ("we already know a venue here") lets
        // someone see one still in review, to claim it rather than add it twice.
        abort_unless($found->status === Venue::APPROVED || auth()->user()?->can('update', $found)
            || ($found->status === Venue::PENDING && request()->hasValidRelativeSignature()), 404);

        $rows = Event::query()
            ->where('status', Event::STATUS_PUBLISHED)
            ->where('hasLocation', true)
            ->whereHas('location', fn ($query) => $query->where('venue_id', $found->id)->where('hiddenLocationToggle', false))
            ->with(['organizer:id,name,slug,status,thumbImagePath', 'location', 'remotelocations', 'images'])
            ->get();

        InstanceAddress::primeFirstDays($rows);

        $rows = $rows->map(fn (Event $run) => RunRow::for($run));

        return view('venues.show', [
            'venue' => $found,
            'claim' => $this->claimOffer($found),
            'current' => $rows->where('bucket', '!=', RunRow::PAST)->sortBy('start')->values(),
            'before' => $rows->where('bucket', RunRow::PAST)->sortByDesc('start')->values(),
        ]);
    }

    /**
     * What the "Claim this venue" button needs, or null when it should not show:
     * a venue already claimed, or someone who can already edit it.
     */
    private function claimOffer(Venue $venue): ?array
    {
        if (! $venue->isClaimable()) {
            return null;
        }

        // Its own keeper has nothing to claim; staff may still claim one as their own.
        $user = auth()->user();
        if ($venue->isKeptBy($user)) {
            return null;
        }

        return [
            'slug' => $venue->slug,
            'name' => $venue->name,
            'signed_in' => (bool) $user,
            'pending' => $user ? $venue->claims()->where('user_id', $user->id)->where('status', VenueClaim::PENDING)->exists() : false,
        ];
    }
}
