<?php

namespace App\Http\Controllers\Api;

use App\Actions\Projects\VenueClaims;
use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\Request;

/** A signed-in person asks to own a venue's page, as a company claims its organizer page. */
class VenueClaimController extends Controller
{
    public function store(Request $request, Venue $venue, VenueClaims $claims)
    {
        $validated = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);

        $claims->create($venue, $request->user(), $validated['message'] ?? null);

        return response()->json(['message' => 'Thanks! We will check and get back to you.'], 201);
    }
}
