<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use App\Http\Requests\StoreOrganizerRequest;
use App\Models\ImageHandler;
use App\Models\Event;
use Illuminate\Support\Facades\Log;

class OrganizerController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified'])->except(['show']);
    }

    public function show(Organizer $organizer)
    {
        if($organizer->status !== 'p') { return redirect('/');}
        return view('Organizers.show', compact('organizer'));
    }

    public function teams()
    {
        $teams = auth()->user()->allTeams();
        return view('Organizers.teams', compact('teams'));
    }

    public function switchTeam(Organizer $organizer)
    {
        auth()->user()->update(['current_team_id' => $organizer->id]);
        return redirect('/hosting/events')->with('success', 'Team switched successfully.');
    }

    public function store(StoreOrganizerRequest $request)
    {
        try {
            // Create the organizer
            $organizer = auth()->user()->organizers()->create($request->validated());
            
            if ($request->hasFile('image')) {
                ImageHandler::saveImage($request->file('image'), $organizer, 800, 800, 'organizer');
            }

            // Update current_team_id for the user
            auth()->user()->update(['current_team_id' => $organizer->id]);

            // Update status for the organizer
            $organizer->update(['status' => 'r']);

            // Check if the user has created organizers before
            $hasPreviousOrganizers = auth()->user()->organizers()->count() > 1;

            if ($hasPreviousOrganizers) {
                return response()->json(['redirect' => url('/hosting/events')], 200);
            } else {
                // Create a new event and return JSON with the redirect URL for event edit page
                $event = Event::newEvent($organizer->id);
                return response()->json(['redirect' => route('event.edit', ['event' => $event->slug])], 200);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create organizer: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create organizer.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

