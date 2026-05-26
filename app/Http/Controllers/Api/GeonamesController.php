<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side proxy for GeoNames lookups so the username doesn't ship in the
 * JS bundle. Returns the same shape the upstream API returns, so the frontend
 * just changes the URL it hits.
 */
class GeonamesController extends Controller
{
    public function timezone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $username = config('services.geonames.username');
        if (! $username) {
            Log::warning('Geonames timezone request without configured username');

            return response()->json(['error' => 'Geonames not configured.'], 503);
        }

        try {
            $response = Http::timeout(8)->get('https://secure.geonames.org/timezoneJSON', [
                'lat' => $validated['lat'],
                'lng' => $validated['lng'],
                'username' => $username,
            ]);

            if (! $response->successful()) {
                Log::warning('Geonames upstream non-2xx', ['status' => $response->status()]);

                return response()->json(['error' => 'Geonames lookup failed.'], 502);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            Log::error('Geonames lookup exception: '.$e->getMessage());

            return response()->json(['error' => 'Geonames lookup failed.'], 502);
        }
    }
}
