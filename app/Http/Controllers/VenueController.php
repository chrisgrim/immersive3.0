<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\SlugRedirect;
use App\Models\Venue;
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

        abort_unless($found->status === Venue::APPROVED, 404);

        $rows = Event::query()
            ->where('status', Event::STATUS_PUBLISHED)
            ->whereHas('location', fn ($query) => $query->where('venue_id', $found->id)->where('hiddenLocationToggle', false))
            ->with(['organizer:id,name,slug,status', 'location', 'remotelocations', 'images'])
            ->get()
            ->map(fn (Event $run) => RunRow::for($run));

        return view('venues.show', [
            'venue' => $found,
            'current' => $rows->where('bucket', '!=', RunRow::PAST)->sortBy('start')->values(),
            'before' => $rows->where('bucket', RunRow::PAST)->sortByDesc('start')->values(),
        ]);
    }
}
