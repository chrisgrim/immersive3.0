<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function show(Event $event)
    {
        // If event is embargoed or not published, redirect to home page instead of 404
        if ($event->status !== 'p') {
            return redirect('/');
        }

        $event->load([
            'currentUserFavorite',
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
            'images',
        ]);

        $event->load(['organizer' => function ($query) {
            $query->withCount(['events' => function ($eventsQuery) {
                $eventsQuery->where('status', 'p')->where('archived', false);
            }])
                ->with(['events' => function ($eventsQuery) {
                    $eventsQuery->where('status', 'p')
                        ->where('archived', false)
                        ->with('currentUserFavorite')
                        ->orderByDesc('updated_at')
                        ->limit(12);
                }]);
        }]);

        $event->append('first_show_tickets');

        return view('events.show', compact('event'));
    }

    public function getOrganizerPaginatedEvents(Organizer $organizer, Request $request)
    {
        return Event::where('status', 'p')
            ->where('organizer_id', $organizer->id)
            ->where('archived', false)
            ->with(['category', 'genres', 'currentUserFavorite'])
            ->orderByRaw('CASE WHEN closingDate >= NOW() THEN 0 ELSE 1 END')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('pageSize', 10));
    }
}
