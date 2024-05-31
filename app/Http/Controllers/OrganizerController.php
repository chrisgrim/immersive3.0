<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use App\Models\Event;
use App\Http\Requests\StoreOrganizerRequest;
use App\Models\ImageHandler;

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

            // Check if an image is provided and handle the image upload
            if ($request->hasFile('image')) {
                ImageHandler::saveImage($request, $organizer, 800, 800, 'organizer');
            }

            // Update current_team_id for the user
            auth()->user()->update(['current_team_id' => $organizer->id]);

            // Update status for the organizer
            $organizer->update(['status' => 'r']);

            return response()->json([
                'message' => 'Organizer created successfully.',
                'organizer' => $organizer
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create organizer.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

