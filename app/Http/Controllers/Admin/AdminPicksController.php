<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PickOfTheWeek;
use Illuminate\Http\Request;

class AdminPicksController extends Controller
{
    public function index()
    {
        return PickOfTheWeek::with(['event'])
            ->latest()
            ->paginate(20);
    }

    public function store(Request $request, Event $event)
    {
        return PickOfTheWeek::create([
            'event_id' => $event->id,
            'admin_id' => auth()->id(),
            'featured_until' => $request->featured_until
        ]);
    }
} 