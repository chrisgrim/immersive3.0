<?php

namespace App\Actions\Projects;

use App\Actions\Admin\ModerateSubmission;
use App\Models\Event;
use App\Models\ProjectLinkRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tells the company that keeps a project's page that a listing is waiting to
 * join it. The 72-hour clock assumes it knows. A request for a standalone
 * listing has no company yet and waits in the moderators' queue instead.
 */
class TellKeeper
{
    public function requestWaiting(ProjectLinkRequest $request): void
    {
        // After commit, so a rolled-back request tells nobody.
        DB::afterCommit(fn () => $this->send($request));
    }

    private function send(ProjectLinkRequest $request): void
    {
        // Read again after commit: the same save may have decided it (a
        // moderator approving the listing into a show, or turning the link
        // down), and a settled request asks nobody anything.
        $request = $request->fresh();

        if ($request === null || ! $request->isPending()) {
            return;
        }

        $event = Event::find($request->event_id);
        $project = $request->project;
        $keeper = $project?->keeper();

        // A held draft is staff's business until it is approved, and the
        // mail is written by whoever is acting: there is nobody in a console.
        if (! $event || ! $keeper || $event->held_for_staff || ! auth()->check()) {
            return;
        }

        $presenter = $event->organizer?->name;
        $url = url("/hosting/show-runs/{$project->slug}/edit");
        $body = "\"{$event->name}\"".($presenter ? " from {$presenter}" : '')
            ." has asked to join \"{$project->name}\". Approve or decline it here: {$url}\n\n"
            .'If it has no answer within '.ProjectLinkRequest::ESCALATE_AFTER_HOURS.' hours it goes to the Everything Immersive moderators.';

        try {
            app(ModerateSubmission::class)->notifyOwner($keeper, $keeper->owner, $body, $body, 'update');
        } catch (\Exception $e) {
            // The request stands, and the moderators still get it at 72 hours.
            report($e);
            Log::warning('Link request notice failed: '.$e->getMessage());
        }
    }
}
