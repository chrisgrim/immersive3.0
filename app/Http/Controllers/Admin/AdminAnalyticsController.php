<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Analytics\SiteAnalyticsReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    /** The admin Analytics page's data: the last `days` (default 30). */
    public function index(Request $request, SiteAnalyticsReport $report)
    {
        return response()->json($report->handle((int) $request->input('days', 30)));
    }
}
