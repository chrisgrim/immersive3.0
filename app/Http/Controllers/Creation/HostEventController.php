<?php

namespace App\Http\Controllers\Creation;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Event;
use App\Models\Events\RemoteLocation;
use App\Models\Events\Show;
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
        $event->load('location', 'contentAdvisories', 'contactLevels', 'mobilityAdvisories', 'advisories','interactive_level', 'remotelocations','genres', 'priceranges', 'shows', 'age_limits');
        return view('Creation.edit', compact('event'));
    }

    public function update(StoreEventRequest $request, Event $event)
    {
        $validatedData = $request->validated();

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

        return response()->json([
            'message' => 'Event updated successfully.',
            'event' => $event->load('shows', 'location')
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
