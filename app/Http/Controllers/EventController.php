<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Events\Show;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\SlugRedirect;
use App\Models\Venue;
use App\Scopes\DateScope;
use App\Support\Analytics\Analytics;
use App\Support\Projects\OrganizerShows;
use App\Support\Projects\ProjectLine;
use App\Support\Projects\ProjectPage;
use App\Support\ShowHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Js;

class EventController extends Controller
{
    /**
     * $event is the last segment of the address, in this order:
     *  1. a live project goes by it (404 unless a run is published);
     *  2. it is an old address, which leads where its record is now;
     *  3. an event goes by it: a published run moves to its address under the
     *     show, a published plain listing is shown here.
     *
     * Every other answer is a real 404 with the site's navigation, not a
     * redirect home: Google counted those redirects as soft 404s. A deleted
     * event gives its slug up when it is deleted (Event::releaseSlug), so it
     * is never found here, and nothing of a removed listing (name or image)
     * is shown to anyone. Draft, in-review and embargoed events 404 the same
     * way, so the slug confirms nothing; that goes for an unpublished run too,
     * which is not redirected to the address under its show.
     */
    public function show(string $event)
    {
        $project = Project::where('slug', $event)->first();

        if ($project) {
            return $this->showProject($project);
        }

        if ($moved = $this->movedTo('events/'.$event)) {
            return $moved;
        }

        $listing = Event::where('slug', $event)->first();

        if (! $listing || $listing->status !== Event::STATUS_PUBLISHED) {
            abort(404);
        }

        // Linked to a show that is gone, its address is still this one.
        if ($listing->isLinked() && $listing->publicPath() !== '/events/'.$listing->slug) {
            return $this->redirectTo($listing->publicPath(), 301);
        }

        return $this->showListing($listing);
    }

    /**
     * A run under its show. Published, it is shown; taken down but not
     * deleted, it 404s. Otherwise an old address leads on, then a mistyped
     * one (or a deleted run's released one) goes to the show's page.
     */
    public function instance(string $project, string $instance)
    {
        $show = Project::where('slug', $project)->first();

        $run = $show
            ? Event::where('project_id', $show->id)->where('instance_slug', $instance)->first()
            : null;

        if ($run) {
            abort_unless($run->status === Event::STATUS_PUBLISHED, 404);

            return $this->showListing($run);
        }

        if ($moved = $this->movedTo("events/{$project}/{$instance}")) {
            return $moved;
        }

        // The first segment may be an old address of the show: same run, new
        // show address. If the show has since become a plain listing, that
        // listing is where its runs' old addresses lead.
        $redirect = SlugRedirect::where('path', 'events/'.$project)->first();

        if ($redirect?->target() instanceof Project && ! $redirect->target()->trashed()) {
            return $this->redirectTo($redirect->target()->publicPath().'/'.$instance, 301);
        }

        if ($moved = $this->movedTo('events/'.$project)) {
            return $moved;
        }

        abort_unless($show !== null, 404);

        return $this->redirectTo($show->publicPath(), 302);
    }

    /** The show's own page, public only while a run is published. */
    private function showProject(Project $project)
    {
        $page = ProjectPage::for($project);

        abort_unless($page['run_count'] > 0, 404);

        // Its own kind of view: not an event page, though it shares the route.
        $request = request();
        $request->attributes->set('analytics.page', 'projects.show');
        $request->attributes->set('analytics.path', $project->publicPath());
        $request->attributes->set('analytics.organizer_id', $project->organizer_id);

        return view('projects.show', ['project' => $project, 'page' => $page]);
    }

    private function movedTo(string $path)
    {
        $destination = SlugRedirect::where('path', $path)->first()?->destination();

        return $destination ? $this->redirectTo(...$destination) : null;
    }

    /** A redirect that keeps the query string, so campaign links survive a move. */
    private function redirectTo(string $path, int $status)
    {
        $query = request()->getQueryString();

        return redirect($query ? $path.'?'.$query : $path, $status);
    }

    private function showListing(Event $event)
    {
        $this->recordView($event, request());

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
        // A secret location's exact point stays off the page: the map draws its
        // area around location_latlon, the public point.
        if ($event->location?->hiddenLocationToggle) {
            $event->location->makeHidden(['latitude', 'longitude']);
        }

        // A secret location's street never reaches the page; the map only
        // draws an area for it (show-map.vue).
        if ($event->location?->hiddenLocationToggle) {
            $event->location->makeHidden(['home', 'street', 'postal_code', 'venue_id', ...($event->location->venue_id ? ['venue'] : [])]);
        }

        // A secret-location listing never reads its venue, however it got one.
        // Sent to Blade rather than Vue: the page's JSON has no use for it.
        $venue = $event->location?->venue_id && ! $event->location->hiddenLocationToggle
            ? Venue::where('status', Venue::APPROVED)->whereNull('merged_into_id')->find($event->location->venue_id)
            : null;

        $pageEvent = Js::from($event);
        $projectLine = ProjectLine::for($event);

        return view('events.show', compact('event', 'pageEvent', 'venue', 'projectLine'));
    }

    /** One analytics note per view of a live event page (Analytics::EVENT_VIEW). */
    private function recordView(Event $event, Request $request): void
    {
        // For RecordPageView, which records this page as a page view.
        $request->attributes->set('analytics.event_id', $event->id);
        $request->attributes->set('analytics.organizer_id', $event->organizer_id);
        $request->attributes->set('analytics.path', $event->publicPath());

        // With page views on, that page view is the event view.
        if (Analytics::isPrefetch($request) || Analytics::captures('page_views')) {
            return;
        }

        $referrer = Analytics::referrer($request);

        Analytics::record(Analytics::EVENT_VIEW, [
            'event_id' => $event->id,
            'source' => $referrer['source'],
            'props' => array_filter(['ref' => $referrer['ref']]),
        ], $request);
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
        return OrganizerShows::paginate($organizer, (int) $request->input('page', 1), (int) $request->input('pageSize', 10));
    }
}
