<?php

namespace App\Http\Controllers\Creation;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Event;
use App\Models\ImageHandler;
use App\Models\Events\RemoteLocation;
use App\Models\Events\ContentAdvisory;
use App\Models\Events\MobilityAdvisory;
use App\Models\Events\Show;
use App\Models\Events\Ticket;
use App\Models\Genre;
use App\Http\Requests\StoreEventRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HostEventController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function edit(Event $event)
    {
        $event->load('location', 'contentAdvisories', 'contactLevels', 'mobilityAdvisories', 'advisories','interactive_level', 'remotelocations','genres', 'priceranges', 'shows', 'age_limits', 'images', 'category','genres');

        $show = $event->shows->first();
        $event->tickets = $show ? $show->tickets : collect();

        return view('Creation.edit', compact('event'));
    }

    public function update(StoreEventRequest $request, Event $event)
    {
        $validatedData = $request->validated();

        // Update event status if provided
        if (isset($validatedData['status'])) {
            $event->status = $validatedData['status'];
            $event->save();
        }

        //strips category if user changes event from remote to in-person or vice versa
        if (isset($validatedData['hasLocation']) && $event->category && 
            $event->category->remote === $validatedData['hasLocation']) {
            $event->category()->dissociate();
        }

        if (isset($validatedData['location'])) {
            $event->location->update($validatedData['location']);
        } else {
            $event->update($validatedData);
        }

        if (isset($validatedData['remotelocations'])) {
            $this->storeRemoteLocations($validatedData['remotelocations'], $event);
        }

        if (isset($validatedData['showtype'])) {
            Show::saveShows($request, $event);
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

        if ($request->has('currentImages')) {
            $currentImages = json_decode($request->input('currentImages', '[]'), true);
            ImageHandler::updateImages($event, $currentImages);
        }

        if ($request->hasFile('images')) {
            $ranks = $request->input('ranks', []);
            foreach ($request->file('images') as $index => $image) {
                $rank = $ranks[$index] ?? 0;
                ImageHandler::saveImage($image, $event, 1200, 800, 'event', $rank);
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

        // Store just the YouTube ID
        if ($request->has('video')) {
            $event->video = $request->video ?: null;
            $event->save();
        }

        // Handle genres
        if (isset($validatedData['genres'])) {
            $event->genres()->sync(
                collect($validatedData['genres'])->pluck('id')
            );
        }

        return response()->json([
            'message' => 'Event updated successfully.',
            'event' => $event->load(
                'shows', 
                'location', 
                'images', 
                'advisories', 
                'mobilityAdvisories', 
                'contentAdvisories', 
                'contactLevels', 
                'interactive_level',
                'category',
                'genres'  // Add genres to the loaded relationships
            )
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
        // Optional: Add authorization check if needed
        // $this->authorize('delete', $event);

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully'
        ]);
    }
}
