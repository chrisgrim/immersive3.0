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
}
