<?php

namespace App\Http\Controllers\Creation;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Event;
use Illuminate\Http\Request;

class HostController extends Controller
{
    public function show()
    {
        // Ensure user has a current team set
        $this->ensureCurrentTeam();
        
        $organizer = auth()->user()->organizer()
            ->withUserRole()
            ->with(['images', 'events' => function ($query) {
                $query->withTrashed()->with(['images', 'clicks']);
            }])
            ->first();

        // Add total clicks calculation for each event
        if ($organizer && $organizer->events) {
            foreach ($organizer->events as $event) {
                $event->total_clicks = $event->clicks->count();
                // Add a flag to identify deleted events
                $event->is_deleted = $event->trashed();
            }
        }
            
        return view('creation.index', compact('organizer'));
    }

    /**
     * Ensures the authenticated user has a current_team_id set
     * If not, sets it to their first team
     * 
     * @return void
     */
    private function ensureCurrentTeam()
    {
        $user = auth()->user();
        
        // Skip if user already has a current_team_id
        if ($user->current_team_id) {
            return;
        }
        
        // Check if user has any teams
        if ($user->teams()->count() > 0) {
            $firstTeam = $user->teams()->first();
            
            // Update the user's current_team_id
            $user->update(['current_team_id' => $firstTeam->id]);
        }
    }

    public function intro()
    {
        return view('creation.started');
    }
}
