<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Events\AgeLimit;
use App\Models\Events\ContactLevel;
use App\Models\Events\ContentAdvisory;
use App\Models\Events\InteractiveLevel;
use App\Models\Events\MobilityAdvisory;
use App\Models\Events\RemoteLocation;
use App\Models\Genre;
use App\Scopes\LatestPublishedFirstScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EventAttributesController extends Controller
{
    /**
     * Get categories, optionally filtered by attendance type
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function categories(Request $request)
    {
        $categories = Category::with('images')
            ->orderBy('name');

        // Support both old 'remote' parameter and new 'attendance_type_id' parameter
        if ($request->has('attendance_type_id')) {
            // New way: filter by attendance_type_id using the applicable_attendance_types array
            $attendanceTypeId = (int) $request->query('attendance_type_id');
            $categories->where(function ($query) use ($attendanceTypeId) {
                $query->whereJsonContains('applicable_attendance_types', $attendanceTypeId)
                    ->orWhereNull('applicable_attendance_types')  // Categories with null applicable_attendance_types support all types
                    ->orWhere('applicable_attendance_types', '[]'); // Categories with empty applicable_attendance_types support all types
            });
        } elseif ($request->has('remote')) {
            // Legacy way: filter by remote flag (0 = in-person, 1 = remote)
            $remote = $request->query('remote');
            $categories->where('remote', $remote);
        }

        return response()->json($categories->get());
    }

    /**
     * Get genres available to the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function genres()
    {
        $genres = Genre::where('admin', true)
            ->orWhere('user_id', auth()->user()->id)
            ->orderBy('name')
            ->get();

        return response()->json($genres);
    }

    /**
     * Get remote locations with optional search and filtering
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function remoteLocations(Request $request)
    {
        $majorPlatforms = ['zoom', 'teams', 'meet', 'webex'];
        $selectedIds = $request->get('selected', []);
        $searchTerm = $request->get('search');
        $limit = $request->get('limit', 10);

        $query = RemoteLocation::query();

        if (! empty($searchTerm)) {
            // Search mode
            return $query
                ->whereNotIn('id', $selectedIds)
                ->where('name', 'like', "%{$searchTerm}%")
                ->orderBy('name')
                ->get();
        }

        // Default mode: Major platforms first, then others
        $results = $query
            ->whereNotIn('id', $selectedIds)
            ->orderByRaw("CASE WHEN slug IN ('zoom', 'teams', 'meet', 'webex') THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json($results);
    }

    /**
     * Public, unauthenticated remote-location search for the At Home nav
     * search bar — scoped to admin=true only (the ~20 curated types like
     * "Zoom", "Telephone", "Physical mail"), not the full organizer-entered
     * list of ~139 (which includes one-off/duplicate free-text entries not
     * fit for public search). Kept separate from remoteLocations() above,
     * which the authenticated event-creation wizard uses against the full
     * list and must keep behaving exactly as it does today.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function publicRemoteLocations(Request $request)
    {
        $majorPlatforms = ['zoom', 'teams', 'meet', 'webex'];
        $searchTerm = $request->get('search');
        $limit = min((int) $request->get('limit', 6), 20);

        // Only types with at least one currently-bookable event — the same
        // "closingDate >= today" eligibility ListingsController's atHome
        // search filters on (see its Query::range()->field('closingDate')
        // call). Without this, the dropdown could offer a curated type
        // (e.g. one nobody's used in months) that returns zero results the
        // moment it's picked — confusing in exactly the way this whole
        // rebuild is meant to fix.
        //
        // Cached for an hour rather than the getActiveCategories()/
        // getActiveGenres() forever-and-explicitly-invalidated pattern: this
        // eligibility set depends on attendance_type_id, closingDate, AND an
        // event's remote-location associations, spread across every event
        // creation/edit path plus the embargo-publish cron — correctly
        // invalidating on every one of those write paths is a lot of surface
        // area to get right, and missing one leaves this silently stale
        // forever. A public, unauthenticated, keystroke-driven nav dropdown
        // doesn't need up-to-the-second accuracy; an hour of staleness is a
        // better failure mode than a permanently-stale cache entry.
        $eligibleLocationIds = Cache::remember('at-home-eligible-remote-location-ids', now()->addHour(), function () {
            // withoutGlobalScope(LatestPublishedFirstScope): that scope
            // orders by published_at and is reapplied at execute time no
            // matter what reorder() does earlier in the chain. Left in place,
            // MySQL rejects the query outright ("Expression #1 of ORDER BY
            // clause is not in SELECT list ... incompatible with DISTINCT"),
            // since that column isn't part of this DISTINCT pluck — and no
            // ordering is needed here anyway, this result only feeds a
            // whereIn(), nothing user-visible. No global scope filters by
            // status, so the explicit ->where('status', 'p') below still has
            // to be here — the actual
            // ES-backed search (Event::shouldBeSearchable()) only ever
            // indexes published events, so an unpublished event's remote
            // location must never be treated as "eligible" here either, or
            // this dropdown offers a type that search then returns zero
            // results for.
            return Event::withoutGlobalScope(LatestPublishedFirstScope::class)
                ->where('status', 'p')
                ->where('attendance_type_id', 2)
                ->where('closingDate', '>=', now())
                ->join('event_remote_location', 'events.id', '=', 'event_remote_location.event_id')
                ->distinct()
                ->pluck('event_remote_location.remote_location_id');
        });

        $query = RemoteLocation::where('admin', true)
            ->whereIn('id', $eligibleLocationIds);

        if (! empty($searchTerm)) {
            $query->where('name', 'like', "%{$searchTerm}%");
        }

        $results = $query
            ->orderByRaw('CASE WHEN slug IN (\''.implode("','", $majorPlatforms).'\') THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'slug']);

        return response()->json($results);
    }

    /**
     * Get all contact levels
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function contactLevels()
    {
        $levels = ContactLevel::orderBy('id')->get();

        return response()->json($levels);
    }

    /**
     * Get all interactive levels
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function interactiveLevels()
    {
        $levels = InteractiveLevel::orderBy('id')->get();

        return response()->json($levels);
    }

    /**
     * Get content advisories available to the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function contentAdvisories()
    {
        $advisories = ContentAdvisory::where('admin', true)
            ->orWhere('user_id', auth()->user()->id)
            ->orderBy('name')
            ->get();

        return response()->json($advisories);
    }

    /**
     * Get mobility advisories available to the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function mobilityAdvisories()
    {
        $advisories = MobilityAdvisory::where('admin', true)
            ->orWhere('user_id', auth()->user()->id)
            ->orderBy('name')
            ->get();

        return response()->json($advisories);
    }

    public function ageLimits()
    {
        return AgeLimit::orderBy('age')->get();
    }

    /**
     * Constructor to ensure authentication
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['publicRemoteLocations']);
    }
}
