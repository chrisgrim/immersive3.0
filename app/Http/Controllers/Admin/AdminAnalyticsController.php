<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Analytics\SiteAnalyticsReport;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\LockTimeoutException;
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
     * The page's search boxes: events by name, or typed places, matching
     * `q` (2 to 100 characters) over the last `days`.
     */
    public function find(Request $request, SiteAnalyticsReport $report)
    {
        $validated = $request->validate([
            'kind' => 'required|in:events,places',
            'q' => 'required|string|min:2|max:100',
            'days' => 'nullable|integer|min:1|max:'.SiteAnalyticsReport::MAX_DAYS,
        ]);
        $days = (int) ($validated['days'] ?? 30);

        return response()->json($validated['kind'] === 'events'
            ? $report->findEvents($validated['q'], $days)
            : $report->findPlaces($validated['q'], $days));
    }
}
