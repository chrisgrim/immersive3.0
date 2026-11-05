<?php

namespace App\Http\Controllers\Creation;

use App\Actions\Projects\RequestLink;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Http\Request;

/** A listing's team asks to join a show, or takes the request back. */
class EventLinkRequestController extends Controller
{
    public function store(Request $request, Event $event, RequestLink $link)
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id', 'required_without:source_event_id'],
            'source_event_id' => ['nullable', 'integer', 'exists:events,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = ! empty($validated['project_id'])
            ? Project::findOrFail($validated['project_id'])
            : Event::findOrFail($validated['source_event_id']);

        $linkRequest = $link->handle($event, $target, $request->user(), $validated['note'] ?? null);

        return response()->json(['link_request' => $linkRequest->only(['id', 'status', 'project_id', 'source_event_id'])], 201);
    }

    public function destroy(Request $request, Event $event, RequestLink $link)
    {
        $link->withdraw($event, $request->user());

        return response()->json(['message' => 'Request removed.']);
    }
}
