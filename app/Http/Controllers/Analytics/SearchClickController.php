<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Support\Analytics\Analytics;
use Illuminate\Http\Request;

/**
 * A click on a search result, sent by the browser with navigator.sendBeacon
 * as the visitor leaves for the event page: which search (search_id), which
 * event, and where in the list it sat (1 = first). Stateless (see the
 * route) and answers 204; bad input is simply not recorded.
 */
class SearchClickController extends Controller
{
    public function __invoke(Request $request)
    {
        $searchId = $request->input('search_id');
        $eventId = filter_var($request->input('event_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $position = filter_var($request->input('position'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);

        if (is_string($searchId) && preg_match(Analytics::SEARCH_ID_PATTERN, $searchId) && $eventId && $position) {
            Analytics::record(Analytics::SEARCH_CLICK, [
                'search_id' => $searchId,
                'event_id' => $eventId,
                'props' => ['position' => $position],
            ], $request);
        }

        return response()->noContent();
    }
}
