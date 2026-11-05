<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Projects\CopyCredits;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class AdminCreditController extends Controller
{
    /** Copies `from_event_id`'s list onto {event}, adding and never replacing. */
    public function copy(Request $request, Event $event, CopyCredits $copy)
    {
        $validated = $request->validate(['from_event_id' => ['required', 'integer', 'exists:events,id']]);

        $added = $copy->handle(Event::findOrFail($validated['from_event_id']), $event, $request->user());

        return response()->json(['added' => $added]);
    }
}
