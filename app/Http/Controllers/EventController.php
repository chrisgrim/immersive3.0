<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Events\Show;
use App\Models\Organizer;
use App\Scopes\DateScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Js;

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
            'age_limits',
            'images',
        ]);

        $this->loadShowsForPage($event);

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

        // Serialized ONCE, into `window.Laravel.page.event` (see
        // show.blade.php); every Vue island on the page binds
        // `:event="pageData.event"` (bladeBridge.js). The page used to inline
        // the full model as an attribute on each of 7 desktop / 5 mobile
        // components, and with every show row loaded a long-running event's
        // page was 2.5 MB (desktop) / 4.1 MB (mobile) of HTML before any
        // asset. Js::from emits a JSON.parse('…') with <, >, & and quotes
        // hex-escaped (and / as \/), so user text can't break out of the script.
        $pageEvent = Js::from($event);

        return view('events.show', compact('event', 'pageEvent'));
    }

    /**
     * The page needs the run's summary (first/last date, count) and the
     * calendar's upcoming days, not every row ever scheduled. One event has
     * 3,542 show rows; the page's components read only `date`.
     *
     * `shows` is replaced with the still-relevant rows (newest first, as the
     * components expect), capped. When the run has ended, the last ten rows
     * are kept so the page can still describe it. `show_summary` carries the
     * whole run's first/last date, its total, the number of upcoming rows
     * (uncapped, so the "N dates remaining" text stays honest if the cap
     * bites) and whether the run uses curtain times, which PHP and Vue must
     * decide from the WHOLE schedule, not the embedded subset.
     */
    private function loadShowsForPage(Event $event): void
    {
        $cutoff = $this->showCutoff($event);

        // withoutGlobalScope(DateScope) + reorder(): global scopes are applied
        // when the query runs, AFTER reorder(), so reorder() alone leaves the
        // scope's ORDER BY on an aggregate query (pointless, and refused by
        // some engines).
        $summary = $event->shows()
            ->withoutGlobalScope(DateScope::class)
            ->reorder()
            ->selectRaw(
                'MIN(date) as first_date, MAX(date) as last_date, COUNT(*) as total, '
                ."SUM(TIME(date) <> '00:00:00') as timed_shows_count, "
                .'SUM(date >= ?) as upcoming_total',
                [$cutoff]
            )
            ->first();

        // Event::usesCurtainTimes() reads this aggregate when present, so
        // localDate() in the Blade partials judges the whole run. Vue gets the
        // same answer as show_summary.curtain_times, so keep this off the wire.
        $event->setAttribute('timed_shows_count', (int) ($summary?->timed_shows_count ?? 0));
        $event->makeHidden('timed_shows_count');

        $event->setAttribute('show_summary', [
            'first_date' => $summary?->first_date ? (string) $summary->first_date : null,
            'last_date' => $summary?->last_date ? (string) $summary->last_date : null,
            'total' => (int) ($summary?->total ?? 0),
            'upcoming_total' => (int) ($summary?->upcoming_total ?? 0),
            'curtain_times' => (int) ($summary?->timed_shows_count ?? 0) > 0,
        ]);

        $columns = ['id', 'event_id', 'date'];
        $cap = (int) config('ei.event_page_max_shows', 2000);

        $upcoming = $event->shows()
            ->select($columns)
            ->where('date', '>=', $cutoff)
            ->reorder('date', 'asc')
            ->limit($cap)
            ->get()
            ->sortByDesc('date')
            ->values();

        if ($upcoming->isEmpty()) {
            $upcoming = $event->shows()
                ->select($columns)
                ->reorder('date', 'desc')
                ->limit(10)
                ->get();
        }

        // first_show_tickets reads the earliest loaded show's tickets and the
        // page asks for it up to nine times; load them once here.
        $upcoming->sortBy('date')->first()?->load('tickets');

        $event->setRelation('shows', $upcoming);
    }

    /**
     * The earliest stored value that can still be "today" for this event.
     * A show at exactly 00:00:00 UTC means that calendar date; any other
     * time is a real UTC instant (see Show::localDay). Today's date-only row
     * is stored as today 00:00 UTC; today's earliest timed row is at the
     * event timezone's start of day. The smaller of the two keeps both.
     */
    private function showCutoff(Event $event): string
    {
        $tz = Show::validTimezone($event->timezone);
        $localToday = Carbon::today($tz);

        // Today's timed rows start at the local start of day (as UTC); today's
        // date-only row is the LOCAL date at 00:00 UTC (not UTC's own date:
        // at 11pm in Los Angeles UTC is already tomorrow).
        $timedStart = $localToday->copy()->utc();
        $dateOnly = Carbon::parse($localToday->toDateString().' 00:00:00', 'UTC');

        return $timedStart->min($dateOnly)->format('Y-m-d H:i:s');
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
