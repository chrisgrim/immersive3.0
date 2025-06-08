<?php

namespace App\Http\Controllers\Creation;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Event;
use App\Services\ImageHandler;
use App\Models\Events\RemoteLocation;
use App\Models\Events\ContentAdvisory;
use App\Models\Events\MobilityAdvisory;
use App\Models\Events\Show;
use App\Models\Events\Ticket;
use App\Models\Genre;
use App\Http\Requests\StoreEventRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\NameChangeRequestService;
use Illuminate\Support\Facades\Cache;

class HostEventController extends Controller
{
    protected $nameChangeService;

    public function __construct(NameChangeRequestService $nameChangeService)
    {
        $this->nameChangeService = $nameChangeService;
    }


    public function edit(Event $event)
    {
        $event->load([
            'shows.tickets',
            'location',
            'contentAdvisories',
            'contactLevels',
            'mobilityAdvisories',
            'advisories',
            'interactive_level',
            'remotelocations',
            'genres',
            'priceranges',
            'age_limits',
            'images',
            'category',
            'genres',
            'videos',
            'nameChangeRequests' => function($query) {  // Add this relationship
                $query->where('status', 'pending')->latest();
            }
        ]);
        return view('creation.edit', compact('event'));
    }

    public function update(StoreEventRequest $request, Event $event)
    {
        $wasPublished = in_array($event->status, ['p', 'e']);
        $oldStatus = $event->status;  // Store original status
        $oldCategoryId = $event->category_id;
        $validatedData = $request->validated();

        // Check for duplicate event names if name is being updated
        if (isset($validatedData['name']) && $validatedData['name'] !== $event->name) {
            $duplicates = $this->checkDuplicateNames($validatedData['name'], $event->id);
            if ($duplicates) {
                return response()->json([
                    'message' => 'Duplicate event name detected',
                    'duplicateEvents' => $duplicates,
                    'warning' => 'An event with a similar name already exists.'
                ], 409); // 409 Conflict
            }
        }

        // Handle attendance type changes (using either hasLocation or attendance_type_id)
        if (isset($validatedData['attendance_type_id']) && $event->category) {
            // Check if category is compatible with the attendance type
            if (!$event->category->supportsAttendanceType($validatedData['attendance_type_id'])) {
                $event->category()->dissociate();
                $validatedData['status'] = '1';
            }
            // Keep hasLocation in sync for backward compatibility
            $validatedData['hasLocation'] = $validatedData['attendance_type_id'] == 1;
        } 
        // Legacy handling for hasLocation
        else if (isset($validatedData['hasLocation']) && $event->category && 
            $event->category->remote === $validatedData['hasLocation']) {
            $event->category()->dissociate();
            $validatedData['status'] = '1';
            // Set the corresponding attendance_type_id
            $validatedData['attendance_type_id'] = $validatedData['hasLocation'] ? 1 : 2;
        }

        // Handle location updates
        if (isset($validatedData['location'])) {
            $event->location->update($validatedData['location']);
            
            // Update the location_latlon in events table using the exact format
            if ($event->location->latitude && $event->location->longitude) {
                $event->update([
                    'location_latlon' => [
                        "lat" => (float)$event->location->latitude,
                        "lon" => (float)$event->location->longitude
                    ]
                ]);
            }
            
            // Update the status if it's included in the request
            if (isset($validatedData['status'])) {
                $event->status = $validatedData['status'];
            }
            
            $event->save();
        } else {
            $event->update($validatedData);
        }

        if (isset($validatedData['remotelocations'])) {
            $this->storeRemoteLocations($validatedData['remotelocations'], $event);
        }

        // Handle timezone directly if provided
        if (isset($validatedData['timezone'])) {
            $event->timezone = $validatedData['timezone'];
            $event->save();
        }

        if (isset($validatedData['showtype'])) {
            Show::saveShows($request, $event);
            Show::updateEvent($request, $event);
        }

        // Handle all advisory-related updates
        if (isset($validatedData['contentAdvisories'])) {
            ContentAdvisory::saveAdvisories($event, $validatedData['contentAdvisories']);
        }

        if (isset($validatedData['mobilityAdvisories'])) {
            MobilityAdvisory::saveAdvisories($event, $validatedData['mobilityAdvisories']);
        }

        // Consolidate all advisory updates
        $advisoryData = [];
        
        if (isset($validatedData['advisories'])) {
            if (isset($validatedData['advisories']['sexual'])) {
                $advisoryData['sexual'] = (bool) $validatedData['advisories']['sexual'];
            }
            
            if (isset($validatedData['advisories']['sexualDescription'])) {
                $advisoryData['sexualDescription'] = $validatedData['advisories']['sexualDescription'];
            }

            if (isset($validatedData['advisories']['audience'])) {
                $advisoryData['audience'] = $validatedData['advisories']['audience'];
            }
        }

        // Add wheelchair status to advisory data
        if (isset($validatedData['wheelchairReady'])) {
            $advisoryData['wheelchairReady'] = $validatedData['wheelchairReady'];
        }

        if (!empty($advisoryData)) {
            $event->advisories()->update($advisoryData);
        }

        if (isset($validatedData['tickets'])) {
            Ticket::handleTickets($request, $event);
        }

        if ($request->has('currentImages') || $request->has('deletedImages') || $request->hasFile('images')) {
            // 1. First, get all current images and their ranks
            $existingImages = $event->images()->orderBy('rank')->get();

            // 2. Handle deletions first
            if ($request->has('deletedImages')) {
                $deletedImages = json_decode($request->input('deletedImages', '[]'), true);
                foreach ($deletedImages as $deletedImagePath) {
                    $image = $existingImages->first(function($img) use ($deletedImagePath) {
                        return $img->large_image_path === $deletedImagePath;
                    });
                    if ($image) {
                        ImageHandler::deleteImage($image);
                        $existingImages = $existingImages->reject(fn($img) => $img->id === $image->id);
                    }
                }
            }

            // 3. Handle reordering of existing images
            if ($request->has('currentImages')) {
                $currentImages = json_decode($request->input('currentImages'), true);
                
                if ($currentImages && count($currentImages) > 0) {
                    // Update ranks of existing images
                    foreach ($currentImages as $image) {
                        // Skip if no ID - new uploads are handled separately
                        if (!isset($image['id'])) continue;
                        
                        $eventImage = $event->images()->find($image['id']);
                        if ($eventImage && isset($image['rank'])) {
                            $eventImage->rank = (int)$image['rank'];
                            $eventImage->save();
                        }
                    }
                }
            }

            // 4. Handle new image uploads
            if ($request->hasFile('images')) {
                $ranks = $request->input('ranks', []);
                $currentRanks = collect(json_decode($request->input('currentImages', '[]'), true))
                    ->pluck('rank')
                    ->toArray();
                
                foreach ($request->file('images') as $index => $image) {
                    $rank = (int)($ranks[$index] ?? 0);
                    
                    // First delete any existing image with this rank
                    $existingImage = $event->images()->where('rank', $rank)->first();
                    if ($existingImage) {
                        ImageHandler::deleteImage($existingImage);
                    }
                    
                    // Save the new image with the correct rank
                    ImageHandler::saveImage(
                        $image, 
                        $event, 
                        ($rank === 0) ? 900 : 1200,  // Width
                        ($rank === 0) ? 1200 : 800,  // Height
                        'event-images',
                        $rank
                    );
                }
                
                // Refresh to get the latest state
                $event->refresh();
            }
        }

        // Handle Contact Level
        if (isset($validatedData['contactLevel'])) {
            $event->contactLevels()->sync([$validatedData['contactLevel']['id']]);
        }

        // Handle Interactive Level
        if (isset($validatedData['interactiveLevel'])) {
            $event->interactive_level_id = $validatedData['interactiveLevel']['id'];
            $event->save();
        }

        // Handle Age Limit
        if (isset($validatedData['ageLimit'])) {
            $event->age_limits_id = $validatedData['ageLimit']['id'];
            $event->save();
        }

        // Handle videos
        if ($request->has('videos')) {
            $videosData = json_decode($request->input('videos'), true);
            
            // Delete existing videos
            $event->videos()->delete();
            
            // Create new videos with the provided data
            foreach ($videosData as $videoData) {
                $event->videos()->create([
                    'platform' => $videoData['platform'],
                    'url' => $videoData['url'],
                    'rank' => $videoData['rank'] ?? 0,
                    // If 'id' in videoData is the platform's video ID (e.g., YouTube ID)
                    // it should not be confused with the database ID
                    'platform_video_id' => $videoData['id'] ?? null
                ]);
            }
            
            // Handle video slideshow preference
            if ($request->has('videoSlideshow')) {
                $event->video = $request->videoSlideshow ?: null;
                $event->save();
            }
        }

        // Handle call to action text
        if ($request->has('call_to_action')) {
            $event->call_to_action = $request->call_to_action;
            $event->save();
        }

        // Handle genres
        if (isset($validatedData['genres'])) {
            Genre::saveGenres($event, $validatedData['genres']);
            
            if ($wasPublished || in_array($event->status, ['p', 'e'])) {
                Cache::forget('active-genres');
            }
        }

        // Check if category changed
        if ($oldCategoryId !== $event->category_id) {
            if ($wasPublished || in_array($event->status, ['p', 'e'])) {
                Cache::forget('active-categories');
            }
        }

        // After any update that might change status
        if ($oldStatus === 'e' && $event->status === 'p') {
            Cache::forget('active-categories');
            Cache::forget('active-genres');
        }

        return response()->json([
            'message' => 'Event updated successfully.',
            'event' => $event->load([
                'shows.tickets', 
                'location', 
                'images', 
                'advisories', 
                'mobilityAdvisories', 
                'contentAdvisories', 
                'contactLevels', 
                'interactive_level',
                'category',
                'genres',
                'videos',
                'age_limits',
                'nameChangeRequests' => function($query) {
                    $query->where('status', 'pending')->latest();
                }
            ])
        ], 200);
    }

    protected function storeRemoteLocations($remoteLocations, $event)
    {
        foreach ($remoteLocations as $loc) {
            RemoteLocation::firstOrCreate(
                ['slug' => Str::slug($loc['name'])],
                [
                    'name' => $loc['name'],
                    'user_id' => auth()->user()->id,
                ]
            );
        }

        $newSync = RemoteLocation::whereIn('slug', collect($remoteLocations)->map(function ($item) {
            return Str::slug($item['name']);
        })->toArray())->get();

        $event->remotelocations()->sync($newSync);
    }

    public function submit(Event $event)
    {
        // Check if event is already submitted/published
        if (in_array($event->status, ['r', 'p', 'e'])) {
            return response()->json([
                'message' => 'Event is already submitted or published.',
            ], 422);
        }

        // Update event status to 'r' (under review)
        $event->status = 'r';
        $event->save();

        return response()->json([
            'message' => 'Event submitted successfully.',
            'event' => $event
        ], 200);
    }

    public function destroy(Event $event)
    {
        $wasPublished = in_array($event->status, ['p', 'e']);
        
        $event->delete();

        if ($wasPublished) {
            Cache::forget('active-categories');
            Cache::forget('active-genres');
        }

        return response()->json([
            'message' => 'Event deleted successfully'
        ]);
    }

    public function create(Request $request)
    {
        $organizerId = $request->input('organizer_id');
        
        // Check unpublished events count (bypass for admins)
        $unpublishedCount = Event::countUnpublishedEvents($organizerId);
        
        if ($unpublishedCount >= 5 && !auth()->user()->isAdmin()) {
            return response()->json([
                'message' => 'You can only have 5 unpublished events at a time.'
            ], 422);
        }

        // If name is provided, check for duplicates
        if ($request->has('name') && !empty($request->name)) {
            $duplicates = $this->checkDuplicateNames($request->name);
            if ($duplicates) {
                return response()->json([
                    'message' => 'Duplicate event name detected',
                    'duplicateEvents' => $duplicates,
                    'warning' => 'An event with a similar name already exists. This may cause confusion for attendees or be rejected during review.'
                ], 409); // 409 Conflict
            }
        }

        $event = Event::newEvent($organizerId);

        return response()->json([
            'message' => 'Event created successfully.',
            'event' => $event
        ], 201);
    }

    /**
     * Check if an event name already exists
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkNameAvailability(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'event_id' => 'nullable|integer|exists:events,id'
        ]);

        $duplicates = $this->checkDuplicateNames(
            $request->name, 
            $request->event_id
        );

        if ($duplicates) {
            return response()->json([
                'available' => false,
                'duplicateEvents' => $duplicates,
                'message' => 'An event with a similar name already exists. This may cause confusion for attendees or be rejected during review.'
            ]);
        }

        return response()->json([
            'available' => true,
            'message' => 'This event name is available.'
        ]);
    }

    public function nameChange(Request $request, Event $event)
    {
        try {
            $wouldBeSlug = \Illuminate\Support\Str::slug($request->requested_name);
            
            // Quick check for any slug conflicts (including soft-deleted)
            $hasConflict = Event::withTrashed()
                ->where('slug', $wouldBeSlug)
                ->where('id', '!=', $event->id)
                ->exists();
                
            if ($hasConflict) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'requested_name' => ['An event with this name was created before. Please choose a different name or change it slightly. If you feel this is an error, please contact us at support@everythingimmersive.com']
                    ]
                ], 422);
            }
            
            // Validate the request
            $validator = \Validator::make($request->all(), [
                'requested_name' => [
                    'required',
                    'string',
                    'max:100',
                    new \App\Rules\UniqueSlugRule($request->requested_name, Event::class, 'slug', $event->id)
                ],
                'current_name' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Process the name change request
            $result = $this->nameChangeService->handleNameChange(
                $event,
                $request->requested_name,
                $request->input('reason')
            );

            return response()->json([
                'message' => $result['message'] ?? 'Name change request submitted successfully',
                'event' => $event->fresh(['nameChangeRequests'])
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to submit name change request: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to submit name change request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if the authenticated user has created events before
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function hasCreatedEvents()
    { 
        return response()->json([
            'hasCreatedEvents' => auth()->user()->events()->count() > 1
        ]);
    }

    public function duplicate(Event $event)
    {
        // Check unpublished events count (bypass for admins)
        $unpublishedCount = Event::countUnpublishedEvents($event->organizer_id);
        
        if ($unpublishedCount >= 5 && !auth()->user()->isAdmin()) {
            return response()->json([
                'message' => 'You can only have 5 unpublished events at a time.'
            ], 422);
        }

        try {
            $newEvent = $event->duplicate();

            return response()->json([
                'message' => 'Event duplicated successfully.',
                'event' => $newEvent->load([
                    'shows.tickets', 
                    'location', 
                    'images', 
                    'advisories', 
                    'mobilityAdvisories', 
                    'contentAdvisories', 
                    'contactLevels', 
                    'interactive_level',
                    'category',
                    'genres'
                ])
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to duplicate event: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to duplicate event.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check for duplicate event names
     * 
     * @param string $name
     * @param int|null $excludeId
     * @return array|null
     */
    protected function checkDuplicateNames($name, $excludeId = null)
    {
        if (empty($name)) {
            return null;
        }

        $duplicateEvents = Event::whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->when($excludeId, function($query) use ($excludeId) {
                return $query->where('id', '!=', $excludeId);
            })
            ->select('id', 'name', 'slug', 'status')
            ->get();

        return $duplicateEvents->isNotEmpty() ? $duplicateEvents : null;
    }

    /**
     * Restore a soft-deleted event
     *
     * @param  string  $slug
     * @return \Illuminate\Http\Response
     */
    public function restore(Event $event)
    {
        // Find the event with trashed records included
        $event = Event::withTrashed()->where('slug', $slug)->firstOrFail();
        
        // Ensure the user has permission to manage this event
        $this->authorize('manage', $event);
        
        // Restore the event
        $event->restore();
        
        return redirect()->route('hosting.dashboard')->with('success', 'Event restored successfully.');
    }
}
