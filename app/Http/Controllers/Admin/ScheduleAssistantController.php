<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Scopes\PublishedScope;
use App\Services\EventScheduleAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleAssistantController extends Controller
{
    /**
     * Admin-only AI assistant for the event-creation wizard's Dates step.
     * Scoped to a single event and to schedule fields only.
     */
    public function __invoke(Request $request, string $slug, EventScheduleAssistant $assistant)
    {
        $user = $request->user();

        // Admin-only (type 'a'). The 'admin' route middleware only enforces
        // moderator, so gate strictly here.
        abort_unless($user && $user->isAdmin(), 403, 'Admin access required.');

        $event = Event::withoutGlobalScope(PublishedScope::class)
            ->where('slug', $slug)
            ->firstOrFail();

        abort_unless($user->can('manage', $event), 403, 'You do not have permission to edit this event.');

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'sometimes|array|max:40',
            'history.*.role' => 'required|string|in:user,assistant',
            'history.*.content' => 'required|string|max:8000',
        ]);

        // The in-process MCP tools resolve the actor from the auth guard;
        // make it explicit so it works regardless of guard configuration.
        Auth::setUser($user);

        $result = $assistant->respond(
            $event,
            $user,
            $validated['history'] ?? [],
            $validated['message'],
        );

        return response()->json($result);
    }
}
