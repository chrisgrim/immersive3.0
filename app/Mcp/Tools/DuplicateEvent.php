<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\LinkEventToProject;
use App\Mcp\Tools\Concerns\FormatsEvents;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\ProjectLinkRequest;
use App\Scopes\LatestPublishedFirstScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Copy an event as a new draft: the description, category, genres, advisories, online platforms and pictures come with it; the location, dates, tickets and ticket link do not. Made for touring shows: one call per new city, then one update-event with the new location (geocode-address first), schedule and tickets. With as_run (default true) the copy is a new run of the same show: it asks to join the show run the event is in (or the event itself; EI\'s review of the copy confirms it). For a live event in no show run, start_show_run=true makes the show run right away from it, which changes that live event\'s address: ask the user first. Set as_run=false for an unrelated event that only shares details. Works on events you can edit (moderators/admins: any).')]
class DuplicateEvent extends Tool
{
    use FormatsEvents;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'event_slug' => 'required|string',
            'name' => 'nullable|string|max:100|regex:/[\p{L}\p{N}]/u',
            'organizer_id' => 'nullable|integer|exists:organizers,id',
            'as_run' => 'nullable|boolean',
            'start_show_run' => 'nullable|boolean',
        ]);

        $source = Event::withoutGlobalScope(LatestPublishedFirstScope::class)
            ->where('slug', $validated['event_slug'])
            ->first();

        // One message whether the slug is unknown or the event is someone else's (as get-event).
        if (! $source || ! $user->can('manage', $source)) {
            return Response::error('No event with that slug that you can edit. Slugs come from list-my-events or list-all-events.');
        }

        // Another company presents this run (a touring show): staff only.
        $organizer = null;
        if (! empty($validated['organizer_id']) && (int) $validated['organizer_id'] !== $source->organizer_id) {
            if (! $user->isModerator()) {
                return Response::error('Only EI staff can copy an event to another organizer.');
            }
            $organizer = Organizer::find($validated['organizer_id']);
        }

        // An event whose organizer is gone has nowhere for a copy to go but a named one.
        $targetId = $organizer?->id ?? $source->organizer_id;
        if (! $targetId) {
            return Response::error('That event has no organizer: pass organizer_id for the copy.');
        }

        // The same draft limit as the web flow, against the organizer the copy goes to.
        $count = Event::countUnpublishedEvents($targetId);
        if ($count >= Event::MAX_UNPUBLISHED_EVENTS && ! $user->isModerator()) {
            return Response::error("That organizer already has {$count} unfinished drafts (limit ".Event::MAX_UNPUBLISHED_EVENTS.'). Submit one for review or delete one first.');
        }

        // A new run of the same show by default: it asks to join the show run
        // the event is in (or, for an event in none, the event itself). Starting
        // a show run FROM a live listing changes that listing's public address,
        // so it only happens when start_show_run is sent (the website's
        // Duplicate pop-up asks the same), and never for the AI crawler.
        $asRun = (bool) ($validated['as_run'] ?? true);
        $crawler = $user->id === (int) config('ei.crawler_user_id');
        $starts = $asRun && ($validated['start_show_run'] ?? false) && ! $crawler
            && ! $source->project_id && in_array($source->status, Event::LIVE_STATUSES, true);
        $started = null;

        try {
            // One transaction, as the website: a copy that fails leaves no show
            // run behind. Search index writes batched, entered at level 0.
            $copy = Event::deferringSearchSync(fn () => DB::transaction(function () use ($source, $asRun, $starts, $user, $organizer, $validated, $crawler, &$started) {
                if ($starts) {
                    $started = app(LinkEventToProject::class)->startFrom($source, $user);
                    $source->refresh();
                }

                $copy = $source->duplicate($asRun);

                $changes = [];
                if (isset($validated['name']) && trim($validated['name']) !== '') {
                    $changes['name'] = trim($validated['name']);
                }
                if ($organizer) {
                    $changes['organizer_id'] = $organizer->id;
                }
                // The AI crawler's drafts wait for staff, out of the organizer's
                // sight until approved (as create-event-draft).
                if ($crawler) {
                    $changes['held_for_staff'] = true;
                }
                if ($changes) {
                    $copy->forceFill($changes)->save();
                }

                return $copy;
            }));
        } catch (ValidationException $e) {
            return Response::error(collect($e->errors())->flatten()->first() ?? 'That copy was refused.');
        } catch (AuthorizationException $e) {
            return Response::error('You cannot start a show run from that event.');
        }

        $joining = ProjectLinkRequest::pending()->where('event_id', $copy->id)->with('project:id,name', 'sourceEvent:id,name')->first();
        $copy = $copy->fresh();
        // Pictures are copied file by file and a failed one is skipped: say what really came.
        $pictures = $copy->images()->count();
        $hasMain = $copy->images()->where('rank', 0)->exists();

        $stillToSet = ['location (geocode-address, then update-event)', 'schedule', 'tickets and ticket link'];
        if (! $hasMain) {
            $stillToSet[] = 'main picture (attach-event-image, rank 0)';
        }
        $stillToSet[] = isset($validated['name']) && trim($validated['name']) !== '' ? 'name, if it should differ' : 'name: it is "'.$copy->name.'", rename it before submitting';

        return Response::json([
            'message' => 'Draft copied.',
            'event' => $this->eventSummary($copy),
            'copied' => ['description', 'tag line', 'category', 'genres', 'advisories', 'online platforms', 'website and button text', 'time zone', "{$pictures} of {$source->images()->count()} pictures"],
            'still_to_set' => $stillToSet,
            'show_run' => $joining ? [
                'joining' => $joining->project?->name ?? $joining->sourceEvent?->name,
                'started_now' => $started !== null,
                'note' => $started
                    ? "A show run page now exists for \"{$source->name}\", whose address moved under it. Deleting this draft on the website undoes it. The copy joins once EI approves it."
                    : 'It joins once EI approves the new event.',
            ] : ($asRun ? [
                'joining' => null,
                'note' => 'Not linked: an event can only join a show once it is live. After the source is published, use update-event with source_event_id (or project_id) on the copy.',
            ] : null),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_slug' => $schema->string()->description('The event to copy.')->required(),
            'name' => $schema->string()->description('Optional name for the copy, e.g. "Magic Table (Jakarta)" (otherwise "<name> (Copy)").'),
            'organizer_id' => $schema->integer()->description('EI staff only: put the copy under another organizer (a touring show presented by another company).'),
            'as_run' => $schema->boolean()->description('Default true: the copy is a new run of the same show. False: just a copy.'),
            'start_show_run' => $schema->boolean()->description('Only with the user\'s OK: for a LIVE event in no show run, make the show run now from it (its public address moves under the show). Without it the copy just asks to join that event.'),
        ];
    }
}
