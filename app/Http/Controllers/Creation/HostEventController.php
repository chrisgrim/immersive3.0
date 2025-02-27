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

        // First handle location type change
        if (isset($validatedData['hasLocation']) && $event->category && 
            $event->category->remote === $validatedData['hasLocation']) {
            $event->category()->dissociate();
            $validatedData['status'] = '1';
        }

        // Handle location updates
        if (isset($validatedData['location'])) {
            $event->location->update($validatedData['location']);
            
            // Sync location data to events.location_latlon
            if ($event->location->latitude && $event->location->longitude) {
                $event->location_latlon = [
                    $event->location->latitude,
                    $event->location->longitude
                ];
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

            // 3. Update ranks of existing images
            if ($request->has('currentImages')) {
                $currentImages = json_decode($request->input('currentImages', '[]'), true);
                foreach ($currentImages as $imageData) {
                    $image = $existingImages->first(function($img) use ($imageData) {
                        return $img->large_image_path === $imageData['url'];
                    });
                    
                    if ($image && $image->rank !== $imageData['rank']) {
                        $image->rank = $imageData['rank'];
                        $image->save();
                    }
                }
            }

            // 4. Handle new image uploads
            if ($request->hasFile('images')) {
                $ranks = $request->input('ranks', []);
                
                foreach ($request->file('images') as $index => $image) {
                    $rank = $ranks[$index] ?? 0;
                    
                    // Find and delete any existing image with this rank
                    $existingImage = $event->images()->where('rank', $rank)->first();
                    if ($existingImage) {
                        ImageHandler::deleteImage($existingImage);
                    }
                    
                    // Save new image with appropriate dimensions
                    if ((int)$rank === 0) {
                        ImageHandler::saveImage($image, $event, 900, 1200, 'event-images');
                    } else {
                        ImageHandler::saveImage($image, $event, 1200, 800, 'event-images');
                    }
                    
                    // Update the rank after saving
                    $newImage = $event->images()->latest()->first();
                    if ($newImage) {
                        $newImage->rank = $rank;
                        $newImage->save();
                    }
                }
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

        // Store just the YouTube ID
        if ($request->has('video')) {
            $event->video = $request->video ?: null;
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
        
        // Check unpublished events count
        $unpublishedCount = Event::countUnpublishedEvents($organizerId);
        
        if ($unpublishedCount >= 5) {
            return response()->json([
                'message' => 'You can only have 5 unpublished events at a time.'
            ], 422);
        }

        $event = Event::newEvent($organizerId);

        return response()->json([
            'message' => 'Event created successfully.',
            'event' => $event
        ], 201);
    }

    public function nameChange(Request $request, Event $event)
    {
        try {
            $request->validate([
                'requested_name' => 'required|string|max:100',
                'current_name' => 'required|string'
            ]);

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

    public function duplicate(Event $event)
    {
        // Check unpublished events count
        $unpublishedCount = Event::countUnpublishedEvents($event->organizer_id);
        
        if ($unpublishedCount >= 5) {
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
}
