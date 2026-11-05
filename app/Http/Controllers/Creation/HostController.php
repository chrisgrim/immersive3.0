<?php

namespace App\Http\Controllers\Creation;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Project;

class HostController extends Controller
{
    public function show()
    {
        $user = auth()->user();

        // Redirect users without teams to the getting-started page
        if (! $user->teams()->exists()) {
            return redirect()->route('hosting.intro')
                ->with('info', 'Please create an organization to start hosting events.');
        }

        // getCurrentOrganizer() handles stale current_team_id by falling back to
        // an owned organizer or any team membership; returns null only if there
        // are truly no organizers at all.
        $currentOrganizer = $user->getCurrentOrganizer();
        if (! $currentOrganizer) {
            return redirect()->route('hosting.intro')
                ->with('info', 'Please create an organization to start hosting events.');
        }

        $organizer = Organizer::withUserRole()
            ->with(['images', 'events' => function ($query) use ($user) {
                $query->withTrashed()->visibleToTeam($user)->with(['images', 'clicks', 'currentUserFavorite']);
            }])
            ->find($currentOrganizer->id);

        // Add total clicks calculation for each event
        if ($organizer && $organizer->events) {
            foreach ($organizer->events as $event) {
                $event->total_clicks = $event->clicks->count();
                // Add a flag to identify deleted events
                $event->is_deleted = $event->trashed();
            }
        }

        // The shows this company keeps the page of, for the dashboard's link.
        $organizer?->setAttribute('kept_projects', Project::where('editor_organizer_id', $organizer->id)
            ->orWhere(fn ($query) => $query->where('organizer_id', $organizer->id)->whereNull('editor_organizer_id'))
            ->withCount(['linkRequests as pending_requests' => fn ($query) => $query->pending()->whereNotNull('submitted_at')])
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Project $project) => $project->only(['id', 'name', 'slug', 'pending_requests']))
            ->values());

        return view('creation.index', compact('organizer'));
    }

    /**
     * Ensures the authenticated user has a current_team_id set
     * If not, sets it to their first team
     *
     * @return void
     */
    private function ensureCurrentTeam()
    {
        $user = auth()->user();

        // Skip if user already has a current_team_id
        if ($user->current_team_id) {
            return;
        }

        // Check if user has any teams
        if ($user->teams()->count() > 0) {
            $firstTeam = $user->teams()->first();

            // Update the user's current_team_id
            $user->update(['current_team_id' => $firstTeam->id]);
        }
    }

    public function intro()
    {
        return view('creation.started');
    }
}
