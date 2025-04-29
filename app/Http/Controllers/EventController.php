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
        // If event is embargoed or not published, redirect to home page instead of 404
        if ($event->status !== 'p') {
            return redirect('/');
        }
        
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
            'shows',
            'age_limits',
            'images'
        ]);
        
        $event->load(['organizer' => function($query) {
            $query->with(['events' => function($eventsQuery) {
                $eventsQuery->where('status', 'p')
                    ->orderByDesc('updated_at');
            }]);
        }]);
        
        $event->append('first_show_tickets');

        return view('events.show', compact('event'));
    }

    public function getOrganizerPaginatedEvents(Organizer $organizer, Request $request)
    {
        return Event::where('status', 'p')->where('organizer_id', $organizer->id)->orderByRaw('closingDate >= NOW() desc')->paginate($request->input('pageSize', 9));
    }




}
