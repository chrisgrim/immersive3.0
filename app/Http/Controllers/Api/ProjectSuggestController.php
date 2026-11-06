<?php

namespace App\Http\Controllers\Api;

use App\Actions\Projects\SuggestShows;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

/** The Name step: is this a new run of a show already on EI? */
class ProjectSuggestController extends Controller
{
    public function __invoke(Request $request, SuggestShows $suggest)
    {
        $this->authorize('host', Event::class);

        return response()->json($suggest->handle((string) $request->query('keywords'), $request->integer('except') ?: null, $request->user()));
    }
}
