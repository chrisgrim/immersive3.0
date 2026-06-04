<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OwnershipClaim;
use App\Services\OwnershipClaimService;
use Illuminate\Http\Request;

class AdminOwnershipClaimController extends Controller
{
    protected $claimService;

    public function __construct(OwnershipClaimService $claimService)
    {
        $this->claimService = $claimService;
    }

    public function index()
    {
        $claims = OwnershipClaim::with(['user', 'organizer.owner'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json([
            'claims' => $claims->map(function ($claim) {
                $organizer = $claim->organizer;
                $enteredBy = $organizer?->owner;

                return [
                    'id' => $claim->id,
                    'message' => $claim->message,
                    'status' => $claim->status,
                    'created_at' => $claim->created_at,
                    'organizer' => $organizer ? [
                        'id' => $organizer->id,
                        'name' => $organizer->name,
                        'slug' => $organizer->slug,
                        'status' => $organizer->status,
                    ] : null,
                    // Who entered the org internally, so an admin can see it was a staff entry.
                    'entered_by' => $enteredBy ? [
                        'name' => $enteredBy->name,
                        'email' => $enteredBy->email,
                    ] : null,
                    // The requester to vet — name + email per Chris.
                    'user' => $claim->user ? [
                        'id' => $claim->user->id,
                        'name' => $claim->user->name,
                        'email' => $claim->user->email,
                    ] : null,
                ];
            }),
        ]);
    }

    public function approve(OwnershipClaim $claim)
    {
        $result = $this->claimService->approve($claim, auth()->user());

        return response()->json(['message' => $result['message']], $result['status']);
    }

    public function reject(Request $request, OwnershipClaim $claim)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $result = $this->claimService->reject($claim, auth()->user(), $validated['reason'] ?? null);

        return response()->json(['message' => $result['message']], $result['status']);
    }
}
