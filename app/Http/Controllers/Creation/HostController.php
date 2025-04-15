<?php

namespace App\Http\Controllers\Creation;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Event;
use Illuminate\Http\Request;

class HostController extends Controller
{
    public function show()
    {
        $organizer = auth()->user()->organizer()
            ->withUserRole()
            ->with(['images', 'events' => function ($query) {
                $query->with(['images', 'clicks']);
            }])
            ->first();

        // Add total clicks calculation for each event
        if ($organizer && $organizer->events) {
            foreach ($organizer->events as $event) {
                $event->total_clicks = $event->clicks->count();
            }
        }
            
        return view('creation.index', compact('organizer'));
    }

    public function intro()
    {
        return view('creation.started');
    }
}
