<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use App\Models\Event;

class OrganizerController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware(['auth', 'verified'])->except(['show', 'message','fetchEvents']);
    //     $this->middleware('can:update,organizer')->except(['store','show','create', 'message', 'fetchEvents']);
    // }

    public function show(Organizer $organizer)
    {
        if($organizer->status !== 'p') { return redirect('/');}
        return view('Organizers.show', compact('organizer'));
    }
}

