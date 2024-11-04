<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\CityList;
use App\Models\Category;
use App\Models\Genre;
use App\Models\Organizer;
use App\Models\StaffPick;
use App\Models\Featured\Dock;
use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Models\Curated\Shelf;
use Session;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Http\Requests\TitleUpdateRequest;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\Cache;



class EventController extends Controller
{


    // public function __construct()
    // {
    //     $this->middleware(['auth', 'verified'])->except('index','show', 'fetch');
    //     $this->middleware('can:update,event')
    //     ->except(['index','create','show','store','fetch']);
    // }

   
    public function show(Event $event)
    {
        if ($event->status !== 'p') {
            return redirect('/');
        }

        // Load relationships directly without caching
        $event->load('category', 'location', 'contentAdvisories', 'contactLevels', 
            'mobilityAdvisories', 'eventreviews', 'staffpick', 'advisories', 
            'interactive_level', 'remotelocations', 'genres', 'priceranges', 
            'organizer', 'shows', 'age_limits', 'images');
        
        $tickets = collect(); // Default empty collection
        if ($event->shows && $event->shows->first()) {
            $tickets = $event->shows->first()->tickets()->orderBy('ticket_price')->get();
        }

        return view('events.show', compact('event', 'tickets'));
    }

    public function getOrganizerPaginatedEvents(Organizer $organizer, Request $request)
    {
        return Event::where('status', 'p')->where('organizer_id', $organizer->id)->orderByRaw('closingDate >= NOW() desc')->paginate($request->input('pageSize', 9));
    }




}
