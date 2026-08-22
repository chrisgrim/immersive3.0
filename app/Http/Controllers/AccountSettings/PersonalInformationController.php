<?php

namespace App\Http\Controllers\AccountSettings;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class PersonalInformationController extends Controller
{
    /**
     * Everything the Personal Information panel needs in one round trip —
     * each row (legal name, preferred name, email, location) has its own
     * PATCH endpoint below, but the view state is fetched together since
     * it's all rendered on one page.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $location = $user->location;

        return response()->json([
            'legal_first_name' => $user->legal_first_name,
            'legal_last_name' => $user->legal_last_name,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            // City-level only — the user_locations table also has street/
            // postal_code columns (dormant, from the earlier full-address
            // form this replaced), but those are intentionally never read
            // or written here. This app has no shipping/billing need for a
            // street address, and the site never displays more than a
            // user's city to anyone but the user themself or an admin.
            'location' => ($location && $location->city) ? [
                'city' => $location->city,
                'region' => $location->region,
                'country' => $location->country,
                'lat' => $location->latitude,
                'lng' => $location->longitude,
            ] : null,
        ]);
    }

    public function updateLegalName(Request $request)
    {
        $validated = $request->validate([
            'legal_first_name' => 'required|string|max:255',
            'legal_last_name' => 'required|string|max:255',
        ]);

        $request->user()->update($validated);

        return response()->json($validated);
    }

    public function updatePreferredName(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:60',
        ]);

        $request->user()->update($validated);

        return response()->json($validated);
    }

    /**
     * City-level location, picked from the same Google Places city
     * autocomplete used elsewhere on the site (nav search, event creation)
     * — never a street address. lat/lng come along for free from the same
     * place selection so nothing here needs its own geocoding call.
     */
    public function updateLocation(Request $request)
    {
        $validated = $request->validate([
            'city' => 'required|string|max:255',
            'region' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $user = $request->user();
        $values = [
            'city' => $validated['city'],
            'region' => $validated['region'] ?? null,
            'country' => $validated['country'] ?? null,
            'latitude' => $validated['lat'] ?? null,
            'longitude' => $validated['lng'] ?? null,
        ];

        try {
            $user->location()->updateOrCreate([], $values);
        } catch (QueryException $e) {
            // updateOrCreate's read-then-write isn't atomic — two concurrent
            // first-time saves (e.g. two open tabs) can both miss the initial
            // lookup and both attempt an insert. The user_locations.user_id
            // unique constraint turns the loser into a duplicate-key error
            // here instead of a silent duplicate row; treat it as "someone
            // just created the row" and update it instead.
            if (! str_contains($e->getMessage(), '1062') && ! str_contains(strtolower($e->getMessage()), 'unique')) {
                throw $e;
            }

            $user->location()->update($values);
        }

        return response()->json([
            'city' => $values['city'],
            'region' => $values['region'],
            'country' => $values['country'],
            'lat' => $values['latitude'],
            'lng' => $values['longitude'],
        ]);
    }
}
