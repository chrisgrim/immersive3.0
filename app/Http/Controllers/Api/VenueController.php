<?php

namespace App\Http\Controllers\Api;

use App\Actions\Projects\FindVenues;
use App\Actions\Projects\ProposeVenue;
use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\Request;

/** The Location step's venue suggestions, and proposing one that is missing. */
class VenueController extends Controller
{
    public function search(Request $request, FindVenues $venues)
    {
        $this->authorize('propose', Venue::class);

        $keywords = trim((string) $request->query('keywords'));

        return response()->json(mb_strlen($keywords) < 2 ? [] : $venues->handle($keywords, $request->user())->values());
    }

    /** The other way round: an address EI already knows as a venue. */
    public function match(Request $request, FindVenues $venues)
    {
        $this->authorize('propose', Venue::class);

        return response()->json(['venue' => $venues->atAddress($request->query('home'), $request->query('street'), $request->query('city'), $request->user())]);
    }

    /** Add a venue: places EI already has that this might be (by name or address), to claim instead. */
    public function known(Request $request, FindVenues $venues)
    {
        $this->authorize('propose', Venue::class);

        $except = Venue::where('slug', (string) $request->query('except'))->value('id');

        return response()->json($venues->known(
            $request->query('name'), $request->query('home'), $request->query('street'), $request->query('city'), $except, $request->user()
        )->values());
    }

    public function store(Request $request, ProposeVenue $propose, FindVenues $venues)
    {
        $this->authorize('propose', Venue::class);
        $validated = $request->validate([...ProposeVenue::RULES, 'event_slug' => ['nullable', 'string']]);

        // Added from a listing's Location step: kept by that listing's company,
        // not by whichever team the person last had selected.
        $for = null;
        if (filled($validated['event_slug'] ?? null)) {
            $for = \App\Models\Event::where('slug', $validated['event_slug'])->first();
            abort_unless($for !== null && $request->user()->can('manage', $for) && $for->organizer_id, 403);
        }
        unset($validated['event_slug']);

        return response()->json(['venue' => $venues->present($propose->handle($validated, $request->user(), $for))], 201);
    }
}
