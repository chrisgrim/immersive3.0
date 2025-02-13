<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\CityList;
use App\Models\Category;
use App\Models\Genre;
use App\Models\Organizer;
use App\Models\StaffPick;
use App\Models\Admin\Dock;
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
    public function show(Event $event)
    {
        abort_if($event->status !== 'p', 404);

        $event->load([
            'category',
            'location',
            'contentAdvisories',
            'contactLevels',
            'mobilityAdvisories',
            'eventreviews',
            'staffpick',
            'advisories',
            'interactive_level',
            'remotelocations',
            'genres',
            'priceranges',
            'organizer',
            'shows.tickets',
            'age_limits',
            'images'
        ]);

        return view('events.show', compact('event'));
    }

    public function getOrganizerPaginatedEvents(Organizer $organizer, Request $request)
    {
        return Event::where('status', 'p')->where('organizer_id', $organizer->id)->orderByRaw('closingDate >= NOW() desc')->paginate($request->input('pageSize', 9));
    }




}
