<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Support\Analytics\Analytics;
use Illuminate\Http\Request;

/**
 * Time on page, sent by the browser (navigator.sendBeacon) each time the
 * page is hidden with the running total, only after 5 visible seconds and
 * at most 10 times (the rollup keeps the largest): the view's id
 * (minted by RecordPageView), visible seconds and how far it scrolled.
 * Stateless (see the route), answers 204; bad input is not recorded.
 */
class PageLeaveController extends Controller
{
    public function __invoke(Request $request)
    {
        $viewId = $request->input('view_id');
        $seconds = filter_var($request->input('seconds'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $depth = filter_var($request->input('depth'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);

        if (Analytics::captures('duration') && is_string($viewId) && preg_match(Analytics::SEARCH_ID_PATTERN, $viewId) && $seconds !== false) {
            Analytics::record(Analytics::PAGE_LEAVE, [
                'view_id' => $viewId,
                'seconds' => min($seconds, 1800),
                'depth' => $depth === false ? null : $depth,
            ], $request);
        }

        return response()->noContent();
    }
}
