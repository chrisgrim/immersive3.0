<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Curated\Community;
use App\Models\NameChangeRequest;

class DashboardController extends Controller
{
    /**
     * Display admin dashboard
     */
    public function index()
    {
        return view('Admin.index', [
            'user' => auth()->user()
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
            'requests' => NameChangeRequest::where('status', 'pending')->count()
        ]);
    }
}
