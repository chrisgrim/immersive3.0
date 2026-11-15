<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Projects\VenueClaims;
use App\Http\Controllers\Controller;
use App\Models\VenueClaim;
use Illuminate\Http\Request;

/** Venue claims, in Admin > Approval Queue > Venues. */
class AdminVenueClaimController extends Controller
{
    public function index()
    {
        $claims = VenueClaim::with(['user', 'venue.owner', 'venue.ownerUser'])
            ->where('status', VenueClaim::PENDING)
            ->latest()
            ->get();

        return response()->json([
            'claims' => $claims->map(fn (VenueClaim $claim) => [
                'id' => $claim->id,
                'message' => $claim->message,
                'created_at' => $claim->created_at,
                'venue' => $claim->venue ? [
                    'name' => $claim->venue->name,
                    'url' => $claim->venue->publicPath(),
                    'city' => $claim->venue->city,
                    // Who looks after it now: a person who claimed it, or the team that added it.
                    'kept_by' => $claim->venue->ownerUser?->name ?? $claim->venue->owner?->name,
                ] : null,
                'user' => $claim->user ? ['name' => $claim->user->name, 'email' => $claim->user->email] : null,
            ])->values(),
        ]);
    }

    public function approve(Request $request, VenueClaim $claim, VenueClaims $claims)
    {
        $claims->approve($claim, $request->user());

        return response()->json(['message' => 'Venue claim approved.']);
    }

    public function reject(Request $request, VenueClaim $claim, VenueClaims $claims)
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $claims->reject($claim, $request->user(), $validated['reason'] ?? null);

        return response()->json(['message' => 'Venue claim rejected.']);
    }
}
