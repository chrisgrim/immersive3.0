<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use App\Http\Requests\StoreOrganizerRequest;
use App\Services\ImageHandler;
use App\Models\Event;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\UpdateOrganizerRequest;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\NameChangeRequestService;

class OrganizerController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified'])->except(['show']);
    }

    public function show(Organizer $organizer)
    {
        if($organizer->status !== 'p') { 
            return redirect('/');
        }
        
        $organizer = $organizer->withPaginatedEvents()
            ->withUserRole()
            ->with(['images', 'nameChangeRequests' => function($query) {
                $query->where('status', 'pending')->latest();
            }])
            ->findOrFail($organizer->id);
        
        return view('Organizers.show', compact('organizer'));
    }

    public function teams()
    {
        $teams = auth()->user()->allTeams()->map(function ($team) {
            $team->events_count = $team->events()->count();
            $team->published_events_count = $team->events()->whereIn('status', ['p', 'e'])->count();
            return $team;
        });
        
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

    public function update(StoreOrganizerRequest $request, Organizer $organizer)
    {
        try {
            $data = $request->validated();
            
            // If name is being updated
            if (isset($data['name']) && $data['name'] !== $organizer->name) {
                $nameChangeService = new NameChangeRequestService();
                $result = $nameChangeService->handleNameChange(
                    $organizer, 
                    $data['name'],
                    $request->input('reason')
                );

                // Remove name from data if it wasn't an admin update
                if (!$result['requiresRefresh']) {
                    unset($data['name']);
                }
            }
            
            // Update other fields if any
            if (!empty($data)) {
                $organizer->update($data);
            }
            
            // Refresh organizer with relationships
            $organizer = Organizer::withUserRole()
                ->with(['images', 'nameChangeRequests' => function($query) {
                    $query->where('status', 'pending');
                }])
                ->find($organizer->id);
            
            return response()->json([
                'message' => $result['message'] ?? 'Organizer updated successfully',
                'organizer' => $organizer
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update organizer: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update organizer.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateImage(Request $request, Organizer $organizer)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:8192',
            ]);

            // Delete existing images if there are any
            if ($organizer->images()->exists()) {
                foreach ($organizer->images as $image) {
                    ImageHandler::deleteImage($image);
                }
            }

            // Save new image
            ImageHandler::saveImage($request->file('image'), $organizer, 800, 800, 'organizer-images');

            // Refresh and load the organizer with images
            $organizer = $organizer->fresh(['images']);
            
            // Get the organizer with user role
            $organizer = Organizer::withUserRole()
                ->with('images')
                ->find($organizer->id);

            return response()->json([
                'message' => 'Image updated successfully',
                'organizer' => $organizer,
                'images' => $organizer->images
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update organizer image: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update image.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

