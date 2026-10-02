<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Events\Show;
use App\Models\Organizer;
use App\Scopes\DateScope;
use App\Support\ShowHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Js;

class EventController extends Controller
{
    /**
     * $event is the slug. A show page nobody can see (no event goes by the
     * slug, or the event isn't published) is a real 404 with the site's
     * navigation, not a redirect home: Google counted those redirects as
     * soft 404s. A deleted event gives its slug up when it is deleted
     * (Event::releaseSlug), so it is never found here, and nothing of a
     * removed listing (name or image) is shown to anyone. Draft, in-review
     * and embargoed events 404 the same way, so the slug confirms nothing.
     */
    public function show(string $event)
    {
        $event = Event::where('slug', $event)->first();

        if (! $event || $event->status !== 'p') {
            abort(404);
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
            'tickets',
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
        // Already sent as first_show_tickets; no need to ship the tiers twice.
        $event->makeHidden('tickets');

        // Serialized ONCE, into `window.Laravel.page.event` (see
        // show.blade.php); every Vue island on the page binds
        // `:event="pageData.event"` (bladeBridge.js). The page used to inline
        // the full model as an attribute on each of 7 desktop / 5 mobile
        // components, and with every show row loaded a long-running event's
        // page was 2.5 MB (desktop) / 4.1 MB (mobile) of HTML before any
        // asset. Js::from emits a JSON.parse('…') with <, >, & and quotes
        // hex-escaped (and / as \/), so user text can't break out of the script.
        // A secret location's street never reaches the page; the map only
        // draws an area for it (show-map.vue).
        if ($event->location?->hiddenLocationToggle) {
            $event->location->makeHidden(['home', 'street', 'postal_code']);
        }
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
     * are kept so the page can still describe it. `past_show_dates` carries
     * the dates that already happened, as bare strings, for the calendars. `show_summary` carries the
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

        // Days more than a year old are in the compact show history, not the
        // rows. They count towards the run (its real first day, its total);
        // the calendars read the runs themselves (the event's show_history). A history day is sent as noon in the event's timezone,
        // which reads as that day under either curtain-time rule.
        $firstDate = $summary?->first_date ? (string) $summary->first_date : null;
        $historyFirst = ShowHistory::firstDay($event->show_history);
        if ($historyFirst !== null && ($firstDate === null || $historyFirst < $event->localDate($firstDate))) {
            $firstDate = Show::storedFromLocalDay($historyFirst, Show::validTimezone($event->timezone));
        }

        $event->setAttribute('show_summary', [
            'first_date' => $firstDate,
            'last_date' => $summary?->last_date ? (string) $summary->last_date : null,
            'total' => (int) ($summary?->total ?? 0) + ShowHistory::count($event->show_history),
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

        $event->setRelation('shows', $upcoming);

        // The calendars also highlight the dates that already happened (a
        // curator paging back through a long run's history relies on it).
        // Those are sent as bare date strings, newest first, so they cost
        // ~32 bytes each on the wire instead of a row; the oldest are dropped
        // past the cap (config/ei.php).
        $pastCap = (int) config('ei.event_page_max_past_dates', 9500);
        $event->setAttribute('past_show_dates', $event->shows()
            ->withoutGlobalScope(DateScope::class)
            ->where('date', '<', $cutoff)
            ->reorder('date', 'desc')
            ->limit($pastCap)
            ->pluck('date')
            ->map(fn ($d) => (string) $d)
            ->all());
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
            ->orderByStillRunningFirst()
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('pageSize', 10));
    }
}
