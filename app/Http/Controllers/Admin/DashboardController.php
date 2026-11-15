<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curated\Community;
use App\Models\Event;
use App\Models\EventSuggestion;
use App\Models\NameChangeRequest;
use App\Models\Organizer;
use App\Models\OwnershipClaim;
use App\Models\Venue;
use App\Models\VenueClaim;

class DashboardController extends Controller
{
    /**
     * Display admin dashboard
     */
    public function index()
    {
        return view('admin.index', [
            'user' => auth()->user(),
        ]);
    }

    /**
     * Get counts of items requiring approval
     */
    public function getApprovalCounts()
    {
        return response()->json([
            'events' => Event::where('status', 'r')->count(),
            'organizers' => Organizer::where('status', 'r')->count(),
            'communities' => Community::where('status', 'r')->count(),
            // "Requests" is a catch-all queue: name-change requests, ownership claims and venue claims.
            'requests' => NameChangeRequest::where('status', 'pending')->count()
                + OwnershipClaim::where('status', 'pending')->count()
                + VenueClaim::where('status', VenueClaim::PENDING)->count(),
            // Venues added by teams, waiting for a look.
            'venues' => Venue::where('status', Venue::PENDING)->whereNull('merged_into_id')->count(),
            'suggestions' => EventSuggestion::where('status', 'pending')->count(),
            'crawler' => Event::crawlerDrafts()->count(),
        ]);
    }
}
