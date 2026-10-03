<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Analytics\SearchConsoleReport;
use App\Actions\Analytics\SiteAnalyticsReport;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    /** The admin Analytics page's data: the last `days` (default 30). */
    public function index(Request $request, SiteAnalyticsReport $report)
    {
        try {
            return response()->json($report->handle((int) $request->input('days', 30)));
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'The report is still being built. Try again in a minute.'], 503);
        }
    }

    /**
     * The page's search boxes: events by name, typed places, or searches
     * that found nothing (places and At Home types), matching
     * `q` (2 to 100 characters) over the last `days`.
     */
    public function find(Request $request, SiteAnalyticsReport $report)
    {
        $validated = $request->validate([
            'kind' => 'required|in:events,places,unmet',
            'q' => 'required|string|min:2|max:100',
            'days' => 'nullable|integer|min:1|max:'.SiteAnalyticsReport::MAX_DAYS,
        ]);
        $days = (int) ($validated['days'] ?? 30);

        return response()->json(match ($validated['kind']) {
            'events' => $report->findEvents($validated['q'], $days),
            'places' => $report->findPlaces($validated['q'], $days),
            'unmet' => $report->findUnmet($validated['q'], $days),
        });
    }

    /** One section in full, for the section view (sorting and filtering happen in the page). */
    public function section(Request $request, SiteAnalyticsReport $report, SearchConsoleReport $google, string $name)
    {
        abort_unless(in_array($name, ['places', 'unmet', 'at_home', 'events', 'sources', 'countries', 'google_queries', 'google_pages'], true), 404);
        $max = str_starts_with($name, 'google_') ? SearchConsoleReport::MAX_DAYS : SiteAnalyticsReport::MAX_DAYS;
        $validated = $request->validate(['days' => 'nullable|integer|min:1|max:'.$max]);
        $days = (int) ($validated['days'] ?? 30);

        if (str_starts_with($name, 'google_')) {
            abort_unless($google->configured(), 404);

            try {
                $rows = $google->section($name, $days);

                // The period those rows were built for, not a fresh read.
                return response()->json(['name' => $name, 'days' => $days, 'period' => $google->lastPeriod(), 'rows' => $rows]);
            } catch (QueryException $e) {
                return $this->googleTooSlow($e);
            }
        }

        try {
            return response()->json(['name' => $name, 'days' => $days, 'rows' => $report->section($name, $days)]);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'The report is still being built. Try again in a minute.'], 503);
        }
    }

    /**
     * The "From Google" block (Search Console, imported nightly): hidden
     * while there is no property set and nothing imported.
     */
    public function google(Request $request, SearchConsoleReport $report)
    {
        // Google keeps 16 months, so the Search page reaches further back
        // than the site's own report.
        $validated = $request->validate(['days' => 'nullable|integer|min:1|max:'.SearchConsoleReport::MAX_DAYS]);

        if (! $report->configured()) {
            return response()->json(['configured' => false]);
        }

        try {
            return response()->json($report->dashboard((int) ($validated['days'] ?? 30)));
        } catch (QueryException $e) {
            return $this->googleTooSlow($e);
        }
    }

    /** A Google read over its 5 s cap (or a database hiccup): say so, not a 500. */
    private function googleTooSlow(QueryException $e)
    {
        report($e);

        return response()->json(['message' => 'The Google numbers took too long to read. Try again in a minute.'], 503);
    }
}
