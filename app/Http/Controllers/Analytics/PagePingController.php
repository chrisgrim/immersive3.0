<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Support\Analytics\Analytics;
use Illuminate\Http\Request;

/**
 * The load ping (navigator.sendBeacon), once per page view: the page loaded
 * in a browser that ran its JavaScript and was shown at least once, so the
 * view was not a script fetching HTML. Carries the view's id (minted by
 * RecordPageView) and whether the browser reports being automated
 * (navigator.webdriver). The flusher turns it into js = 1 on that page
 * view. Stateless (see the route), answers 204; bad input is not recorded.
 */
class PagePingController extends Controller
{
    public function __invoke(Request $request)
    {
        $viewId = $request->input('view_id');

        if (Analytics::pingsOn() && is_string($viewId) && preg_match(Analytics::SEARCH_ID_PATTERN, $viewId)) {
            Analytics::record(Analytics::PAGE_PING, [
                'view_id' => $viewId,
                'webdriver' => (string) $request->input('wd') === '1' ? 1 : 0,
            ], $request);
        }

        return response()->noContent();
    }
}
