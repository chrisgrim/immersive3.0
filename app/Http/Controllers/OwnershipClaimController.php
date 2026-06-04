<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use App\Services\OwnershipClaimService;
use Illuminate\Http\Request;

class OwnershipClaimController extends Controller
{
    protected $claimService;

    public function __construct(OwnershipClaimService $claimService)
    {
        $this->claimService = $claimService;
    }

    /**
     * A logged-in user requests ownership of a pre-entered (staff-owned, externally-unowned)
     * organizer. Eligibility is fully re-validated server-side in the service.
     */
    public function store(Request $request, Organizer $organizer)
    {
        $validated = $request->validate([
            'message' => 'nullable|string|max:1000',
        ]);

        $result = $this->claimService->create($organizer, $request->user(), $validated['message'] ?? null);

        return response()->json(['message' => $result['message']], $result['status']);
    }
}
