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
        $event->load('location', 'contentAdvisories', 'contactLevels', 'mobilityAdvisories', 'advisories','interactive_level', 'remotelocations','genres', 'priceranges', 'shows', 'age_limits', 'images');

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

        if (isset($validatedData['contentAdvisories'])) {
            ContentAdvisory::saveAdvisories($event, $validatedData['contentAdvisories']);
        }

        if (isset($validatedData['mobilityAdvisories'])) {
            MobilityAdvisory::saveAdvisories($event, $validatedData['mobilityAdvisories']);
            
            // Update wheelchair status
            if (isset($validatedData['wheelchairReady'])) {
                $event->advisories()->update([
                    'wheelchairReady' => $validatedData['wheelchairReady']
                ]);
            }
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

        // Update advisories
        if (isset($validatedData['advisories'])) {
            $advisoryData = [];
            
            if (isset($validatedData['advisories']['sexual'])) {
                $advisoryData['sexual'] = $validatedData['advisories']['sexual'];
            }
            
            if (isset($validatedData['advisories']['sexualDescription'])) {
                $advisoryData['sexualDescription'] = $validatedData['advisories']['sexualDescription'];
            }

            if (isset($validatedData['advisories']['audience'])) {
                $advisoryData['audience'] = $validatedData['advisories']['audience'];
            }

            if (!empty($advisoryData)) {
                $event->advisories()->update($advisoryData);
            }
        }

        // Update content advisories
        if (isset($validatedData['contentAdvisories'])) {
            ContentAdvisory::saveAdvisories($event, $validatedData['contentAdvisories']);
        }

        // Store just the YouTube ID
        if ($request->has('video')) {
            $event->video = $request->video ?: null;
            $event->save();
        }

        return response()->json([
            'message' => 'Event updated successfully.',
            'event' => $event->load('shows', 'location', 'images', 'advisories', 'mobilityAdvisories', 'contentAdvisories', 'contactLevels', 'interactive_level')
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


}
