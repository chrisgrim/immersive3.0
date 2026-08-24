<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * $event is the slug, not implicit route-model binding — implicit
     * binding's default query excludes soft-deleted rows (SoftDeletes'
     * global scope), so a link to an event that was since deleted (e.g. a
     * notification created back when it still existed — see
     * SavedEventNewDatesNotification::toDatabase()/
     * FollowedOrganizerNewEventNotification::toDatabase(), which store the
     * slug at notify time) would 404 outright instead of explaining what
     * happened. events.show-deleted already existed fully built (meta tags,
     * mobile/desktop layouts) for exactly this, just never wired to a route.
     */
    public function show(string $event)
    {
        $event = Event::withTrashed()->where('slug', $event)->firstOrFail();

        if ($event->trashed()) {
            // published_at is set once on approval/embargo-publish (see
            // AdminEventController::approve()/PublishEventsCommand) and never
            // cleared afterward, so it's a reliable "was this ever actually
            // public" flag independent of status at delete time. Without this
            // check, a deleted draft/rejected event (HostEventController::
            // destroy() allows soft-deleting from any status) would render
            // the "removed" page — including its name/image — for something
            // that never had a public page to remove in the first place.
            abort_unless($event->published_at !== null, 404);

            $event->load('images');

            return view('events.show-deleted', compact('event'));
        }

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

        // Computed once and reused everywhere show.blade.php/show-mobile.
        // blade.php/header-mobile.blade.php bind `:event="..."` on a Vue
        // component — those templates used to write `{{ $event }}` inline
        // at each of up to 7 (desktop) / 5 (mobile) call sites, and Blade's
        // `{{ }}` re-serializes the ENTIRE model (every loaded relation,
        // including `shows` — some of this app's long-running events carry
        // 2,000+ show rows) from scratch every single time it's written.
        // For a heavily-recurring event that was measured taking >1.4s of
        // pure render time for a single request; this cuts it to one pass.
        $eventJson = e($event);

        return view('events.show', compact('event', 'eventJson'));
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
