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

        return response()->json(mb_strlen($keywords) < 2 ? [] : $venues->handle($keywords)->values());
    }

    /** The other way round: an address EI already knows as a venue. */
    public function match(Request $request, FindVenues $venues)
    {
        $this->authorize('propose', Venue::class);

        return response()->json(['venue' => $venues->atAddress($request->query('home'), $request->query('street'), $request->query('city'))]);
    }

    public function store(Request $request, ProposeVenue $propose, FindVenues $venues)
    {
        $this->authorize('propose', Venue::class);

        return response()->json(['venue' => $venues->present($propose->handle($request->validate(ProposeVenue::RULES), $request->user()))], 201);
    }
}
