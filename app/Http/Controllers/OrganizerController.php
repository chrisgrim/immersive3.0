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
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;

class OrganizerController extends Controller
{
    public function show(Organizer $organizer)
    {
        // Load the organizer with all needed relations in one query
        $organizer = Organizer::where('id', $organizer->id)
            ->withPaginatedEvents()
            ->withUserRole()
            ->withCount(['events' => function($query) {
                $query->where('status', 'p')->where('archived', false);
            }])
            ->with(['images', 'nameChangeRequests' => function($query) {
                $query->where('status', 'pending')->latest();
            }])
            ->firstOrFail();
        
        // Since 'show' is excepted from auth middleware, we need to check for user
        if ($organizer->status !== 'p' && 
            (!auth()->check() || 
            (!auth()->user()->isAdmin() && !auth()->user()->belongsToOrganization($organizer)))
        ) { 
            return redirect('/');
        }
        
        return view('organizers.show', compact('organizer'));
    }

    public function edit(Organizer $organizer)
    {
        $organizer = $organizer->withPaginatedEvents()
            ->withUserRole()
            ->with(['images', 'nameChangeRequests' => function($query) {
                $query->where('status', 'pending')->latest();
            }])
            ->findOrFail($organizer->id);
        
        return view('organizers.edit', compact('organizer'));
    }

    public function teams(Request $request): View
    {
        return view('organizers.teams');
    }

    public function switchTeam(Organizer $organizer)
    {
        auth()->user()->update(['current_team_id' => $organizer->id]);
        return redirect('/hosting/events')->with('success', 'Team switched successfully.');
    }

    public function store(StoreOrganizerRequest $request)
    {
        try {
            // Create the organizer with user_id
            $organizer = auth()->user()->organizers()->create($request->validated());
            
            // Also attach the creating user as owner in pivot table
            $organizer->users()->attach(auth()->id(), ['role' => 'owner']);

            if ($request->hasFile('image')) {
                ImageHandler::saveImage($request->file('image'), $organizer, 800, 800, 'organizer-images');
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
                return response()->json(['redirect' => route('hosting.event.edit', ['event' => $event->slug])], 200);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create organizer: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create organizer.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function requestNameChange(Request $request, Organizer $organizer)
    {
        try {
            ['requested_name' => $requestedName, 'current_name' => $currentName] = $request->validate([
                'requested_name' => [
                    'required',
                    'string',
                    'max:80'
                ],
                'current_name' => 'required|string'
            ]);

            $nameChangeService = new NameChangeRequestService();
            $result = $nameChangeService->handleNameChange(
                $organizer,
                $requestedName,
                'User requested name change'
            );

            // Refresh organizer with relationships
            $organizer = Organizer::withUserRole()
                ->with(['images', 'nameChangeRequests' => function($query) {
                    $query->where('status', 'pending');
                }])
                ->find($organizer->id);

            return response()->json([
                'message' => $result['message'] ?? 'Name change request submitted successfully',
                'organizer' => $organizer
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to submit name change request: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to submit name change request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(StoreOrganizerRequest $request, Organizer $organizer)
    {
        try {
            $data = $request->validated();
            
            // Only remove name from update if status is 'p' and name is different
            if ($organizer->status === 'p' && isset($data['name']) && $data['name'] !== $organizer->name) {
                unset($data['name']);
            }
            
            // Handle image removal or update
            if ($request->hasFile('image')) {
                // Delete existing images if there are any
                if ($organizer->images()->exists()) {
                    foreach ($organizer->images as $image) {
                        ImageHandler::deleteImage($image);
                    }
                }

                // Save new image
                ImageHandler::saveImage($request->file('image'), $organizer, 800, 800, 'organizer-images');
            } elseif ($request->has('remove_image')) {
                // Delete existing images without adding new ones
                if ($organizer->images()->exists()) {
                    foreach ($organizer->images as $image) {
                        ImageHandler::deleteImage($image);
                    }
                }
            }
            
            // Remove image-related keys from data array
            unset($data['image']);
            unset($data['remove_image']);
            
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
                'message' => 'Organizer updated successfully',
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

    public function searchTeams(Request $request)
    {
        try {
            $query = auth()->user()->teams()
                ->with('images')
                ->withCount(['events', 'events as published_events_count' => function ($query) {
                    $query->whereIn('status', ['p', 'e']);
                }]);

            if ($search = $request->get('q')) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            $teams = $query->paginate(40)->through(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'events_count' => $team->events_count,
                    'published_events_count' => $team->published_events_count,
                    'role' => $team->membership->role,
                    'images' => $team->images,
                    'thumbImagePath' => $team->thumbImagePath,
                    'largeImagePath' => $team->largeImagePath,
                    'created_at' => $team->created_at
                ];
            });

            return response()->json([
                'teams' => $teams,
                'current_team_id' => auth()->user()->current_team_id
            ]);

        } catch (\Exception $e) {
            Log::error('Error in searchTeams:', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function submit(Organizer $organizer)
    {
        try {
            // Update the organizer status to 'r' (review)
            $organizer->update(['status' => 'r']);
            
            // Refresh organizer with relationships
            $organizer = Organizer::withUserRole()
                ->with(['images'])
                ->find($organizer->id);
            
            return response()->json([
                'message' => 'Organizer submitted successfully',
                'organizer' => $organizer
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to submit organizer: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to submit organizer.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkNameAvailability(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:80'
        ]);

        $existingOrganizers = Organizer::where('name', 'LIKE', $request->name)
            ->where('status', '!=', 'd') // Exclude deleted/deactivated
            ->select('id', 'name', 'slug', 'description')
            ->get();

        if ($existingOrganizers->isEmpty()) {
            return response()->json([
                'available' => true,
                'message' => 'This organization name is available.'
            ]);
        }

        return response()->json([
            'available' => false,
            'existing_organizations' => $existingOrganizers,
            'message' => 'One or more organizations with this name already exist.'
        ]);
    }

    public function index()
    {
        return view('organizers.index');
    }
}

