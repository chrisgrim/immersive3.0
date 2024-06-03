<?php

namespace App\Http\Controllers\Creation;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Event;
use App\Http\Requests\StoreEventRequest;
use Illuminate\Http\Request;


class HostEventController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function edit(Event $event)
    {
        $event->load('location', 'contentAdvisories', 'contactLevels', 'mobilityAdvisories', 'advisories','interactive_level', 'remotelocations', 'timezone','genres', 'priceranges', 'shows', 'age_limits');
        return view('Creation.edit', compact('event'));
    }

    public function update(StoreEventRequest $request, Event $event)
    {
        $validatedData = $request->validated();

        $event->update($validatedData);

        return response()->json([
            'message' => 'Event updated successfully.',
            'event' => $event
        ], 200);
    }


}
