<?php

namespace App\Http\Controllers\Creation;

use App\Actions\Events\CheckDuplicateEventNames;
use App\Actions\Events\UpdateEventAction;
use App\Actions\Projects\DissolveProject;
use App\Actions\Projects\LinkEventToProject;
use App\Actions\Projects\PrefillFromShow;
use App\Actions\Projects\RequestLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Project;
use App\Services\NameChangeRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HostEventController extends Controller
{
    protected $nameChangeService;

    public function __construct(NameChangeRequestService $nameChangeService)
    {
        $this->nameChangeService = $nameChangeService;
    }

    /**
     * A published event whose run ended more than Event::EDIT_WINDOW_DAYS ago
     * is a historical record. Its organizers can no longer edit, rename or
     * delete it; the one route left open on purpose is duplicate(), which
     * starts the new listing the dashboard's modal offers. Moderators and
     * admins stay exempt for corrections and backfills.
     *
     * Inside the window a finished run is still editable, so an organizer can
     * add dates to a just-ended event and bring it back at its own URL rather
     * than as a new listing; Show::saveShows() keeps the past shows from
     * being erased meanwhile. The rule itself lives on the model
     * (Event::isEditLockedFor), shared with the MCP tools, and is appended
     * to the event JSON as isEditLocked so the dashboard can explain before
     * it asks.
     */
    private function assertEditable(Event $event): void
    {
        abort_if($event->isEditLockedFor(auth()->user()), 403, Event::EDIT_LOCKED_MESSAGE);
    }

    public function edit(Event $event)
    {
        // A page, not an API call: rather than a bare 403 wall, land on the
        // dashboard with the lock explained and the duplicate offered. Every
        // other Edit link ends up here too (the public event page, admin
        // lists, inbox links, bookmarks), so one modal covers them all.
        if ($event->isEditLockedFor(auth()->user())) {
            return redirect()->route('hosting.dashboard', ['locked' => $event->slug]);
        }

        // Older events keep their picture only on the event row (largeImagePath),
        // from before pictures had their own rows. The image step reads the rows,
        // so it showed an empty box. Give that picture its row once, as the main one.
        // Locked on the event row, so two tabs opening at once make one row, not two.
        if ($event->largeImagePath && ! $event->images()->exists()) {
            DB::transaction(function () use ($event) {
                Event::whereKey($event->id)->lockForUpdate()->first();

                if (! $event->images()->exists()) {
                    $event->images()->create([
                        'large_image_path' => $event->largeImagePath,
                        // The column is required; an old row without a thumb uses the large picture.
                        'thumb_image_path' => $event->thumbImagePath ?: $event->largeImagePath,
                        'rank' => 0,
                    ]);
                }
            });
        }

        $event->load([
            'shows',
            'tickets',
            'location',
            'contentAdvisories',
            'contactLevels',
            'mobilityAdvisories',
            'advisories',
            'interactive_level',
            'remotelocations',
            'genres',
            'priceranges',
            'age_limits',
            'images',
            'category',
            'genres',
            'videos',
            // Its organizer's slug: the list button goes back to that organization's listings.
            'organizer',
            'nameChangeRequests' => function ($query) {  // Add this relationship
                $query->where('status', 'pending')->latest();
            },
        ]);

        // What the Name and Review steps show.
        $linkRequest = $event->pendingLinkRequest();
        $event->setAttribute('link_request', $linkRequest ? [
            'id' => $linkRequest->id,
            'show' => $linkRequest->project?->name ?? $linkRequest->sourceEvent?->name,
            'details_confirmed' => $linkRequest->details_confirmed_at !== null,
            // For the bottom bar's show run picker.
            'project_id' => $linkRequest->project_id,
            'image' => $linkRequest->project?->largeImagePath
                ?? $linkRequest->project?->runs()->where('status', Event::STATUS_PUBLISHED)->whereNotNull('largeImagePath')->reorder('id')->value('largeImagePath')
                ?? $linkRequest->sourceEvent?->largeImagePath,
        ] : null);

        // For the Settings panel: the show run it is already part of, and whether
        // this user may take it out (they keep that show run: ProjectPolicy::decideLink).
        $project = $event->project_id ? Project::find($event->project_id) : null;
        $event->setAttribute('run_of', $project ? [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'kept' => auth()->user()->can('decideLink', $project),
        ] : null);

        return view('creation.edit', compact('event'));
    }

    /**
     * Dates for a warning, the first ten and a count of the rest: a run with
     * decades of history can put thousands in one of these lists.
     *
     * @param  array<int, string>  $dates
     */
    private static function dateList(array $dates): string
    {
        $shown = implode(', ', array_slice($dates, 0, 10));

        return count($dates) > 10 ? $shown.' and '.(count($dates) - 10).' more' : $shown;
    }

    public function update(StoreEventRequest $request, Event $event, UpdateEventAction $updateEvent)
    {
        $this->assertEditable($event);
        // The wizard already stops both of these; this is the server's half,
        // so a direct request cannot get round it.
        abort_if($event->isInReviewFor(auth()->user()), 403, Event::IN_REVIEW_MESSAGE);

        $validatedData = $request->validated();

        if ($event->renameNeedsReviewFor(auth()->user(), $validatedData['name'] ?? null)) {
            throw ValidationException::withMessages(['name' => Event::RENAME_NEEDS_REVIEW_MESSAGE]);
        }

        // Check for duplicate event names if name is being updated (skip if user acknowledged)
        if (isset($validatedData['name']) && $validatedData['name'] !== $event->name && ! $request->boolean('acknowledge_duplicate')) {
            $duplicates = app(CheckDuplicateEventNames::class)->handle($validatedData['name'], $event->id, auth()->user(), $event->sameShowIds());
            if ($duplicates) {
                return response()->json([
                    'message' => 'Duplicate event name detected',
                    'duplicateEvents' => $duplicates,
                    'warning' => 'An event with a similar name already exists.',
                ], 409); // 409 Conflict
            }
        }

        $event = $updateEvent->handle($event, $validatedData, $request);

        $response = [
            'message' => 'Event updated successfully.',
            'event' => $event->load(UpdateEventAction::editorRelations()),
        ];

        // See UpdateEventAction::$preservedPastDates — normally unreachable
        // through the wizard's own calendar UI (past dates aren't
        // selectable there), but a direct API call could still attempt it.
        $warnings = [];

        if (! empty($updateEvent->preservedPastDates)) {
            $warnings[] = 'Dates that have already passed ('
                .self::dateList($updateEvent->preservedPastDates)
                .') were kept and not removed. Only admins and moderators can remove past dates.';
        }

        // The mirror refusal — see UpdateEventAction::$rejectedPastDates.
        if (! empty($updateEvent->rejectedPastDates)) {
            $warnings[] = 'Dates in the past ('
                .self::dateList($updateEvent->rejectedPastDates)
                .') were not added. Only admins and moderators can add dates that have already passed.';
        }

        // See UpdateEventAction::$embargoRefused.
        if ($updateEvent->embargoRefused) {
            $warnings[] = 'The embargo was not applied: this event\'s run has already ended. '
                .'Add upcoming dates in the same save to embargo a new run. Only admins and moderators can embargo a finished event.';
        }

        if ($warnings) {
            $response['warning'] = implode(' ', $warnings);
        }

        return response()->json($response, 200);
    }

    public function submit(Event $event)
    {
        // Check if event is already submitted/published
        if (in_array($event->status, ['r', 'p', 'e'])) {
            return response()->json([
                'message' => 'Event is already submitted or published.',
            ], 422);
        }

        // A copy starts from an earlier run: the safety details are looked at
        // again before it goes in, whoever submits it.
        if ($event->needsDetailsConfirmed()) {
            return response()->json(['message' => Event::CONFIRM_DETAILS_MESSAGE], 422);
        }

        // Update event status to 'r' (under review)
        $event->status = 'r';
        $event->save();

        // Notify admins (who haven't opted out) that an event entered the review queue.
        app(\App\Services\AdminSubmissionNotifier::class)->eventSubmitted($event);

        return response()->json([
            'message' => 'Event submitted successfully.',
            'event' => $event,
        ], 200);
    }

    public function destroy(Event $event)
    {
        $this->assertEditable($event);

        $wasPublished = in_array($event->status, Event::LIVE_STATUSES);
        $event->delete();
        app(DissolveProject::class)->afterEventDeleted($event, request()->user());

        if ($wasPublished) {
            Cache::forget('active-categories');
            Cache::forget('active-genres');
        }

        return response()->json([
            'message' => 'Event deleted successfully',
        ]);
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'organizer_id' => 'required|integer|exists:organizers,id',
            // "Add a run" on the dashboard: the new event asks to join this show run.
            'project_id' => 'nullable|integer',
        ]);

        // The route's can:host only asks "does this user belong to any
        // team?". Without this, a member of one organizer could create
        // drafts under any other organizer by sending its id.
        $organizer = Organizer::findOrFail($validated['organizer_id']);
        $this->authorize('edit', $organizer);
        $organizerId = $organizer->id;

        // Check unpublished events count (bypass for admins)
        $unpublishedCount = Event::countUnpublishedEvents($organizerId);

        if ($unpublishedCount >= Event::MAX_UNPUBLISHED_EVENTS && ! auth()->user()->isAdmin()) {
            return response()->json([
                'message' => 'You can only have '.Event::MAX_UNPUBLISHED_EVENTS.' unpublished events at a time.',
            ], 422);
        }

        // If name is provided, check for duplicates (skip if user acknowledged)
        if ($request->has('name') && ! empty($request->name) && ! $request->boolean('acknowledge_duplicate')) {
            $duplicates = app(CheckDuplicateEventNames::class)->handle($request->name, null, auth()->user());
            if ($duplicates) {
                return response()->json([
                    'message' => 'Duplicate event name detected',
                    'duplicateEvents' => $duplicates,
                    'warning' => 'An event with a similar name already exists. This may cause confusion for attendees or be rejected during review.',
                ], 409); // 409 Conflict
            }
        }

        if (empty($validated['project_id'])) {
            $event = Event::newEvent($organizerId);
        } else {
            // "Add a run": the event and its request to join are made together,
            // so a refused request leaves no empty draft behind (a missing and a
            // hidden show answer alike, as RequestLink does).
            $project = Project::find($validated['project_id']);
            if ($project === null) {
                throw ValidationException::withMessages(['project' => RequestLink::UNAVAILABLE]);
            }

            $event = DB::transaction(function () use ($organizerId, $project, $request) {
                $event = Event::newEvent($organizerId);
                app(RequestLink::class)->handle($event, $project, $request->user());

                return $event;
            });

            // Then what carries over from the show and this company's earlier run.
            // Only a nicety: a failure here never undoes the new run.
            try {
                app(PrefillFromShow::class)->handle($event, $project, $request->user());
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'message' => 'Event created successfully.',
            'event' => $event,
        ], 201);
    }

    /**
     * Check if an event name already exists
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkNameAvailability(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'event_id' => 'nullable|integer|exists:events,id',
        ]);

        $duplicates = app(CheckDuplicateEventNames::class)->handle(
            $request->name,
            $request->event_id,
            auth()->user()
        );

        if ($duplicates) {
            return response()->json([
                'available' => false,
                'duplicateEvents' => $duplicates,
                'message' => 'An event with a similar name already exists. This may cause confusion for attendees or be rejected during review.',
            ]);
        }

        return response()->json([
            'available' => true,
            'message' => 'This event name is available.',
        ]);
    }

    public function nameChange(Request $request, Event $event)
    {
        $this->assertEditable($event);

        try {
            // Refuse a name another listing on EI already goes by. The slug
            // isn't the test: it can carry a suffix, and it belongs to the URL,
            // which Event::finalSlug keeps unique when the change is applied.
            if (Event::nameTakenOnSite((string) $request->requested_name, $event->id, $event->sameShowIds())) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'requested_name' => ['Another listing on EI already has this name. Please choose a different name or change it slightly. If you feel this is an error, please contact us at support@everythingimmersive.com'],
                    ],
                ], 422);
            }

            // Validate the request
            $validator = \Validator::make($request->all(), [
                'requested_name' => [
                    'required',
                    'string',
                    'max:100',
                ],
                'current_name' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Process the name change request
            $result = $this->nameChangeService->handleNameChange(
                $event,
                $request->requested_name,
                $request->input('reason')
            );

            return response()->json([
                'message' => $result['message'] ?? 'Name change request submitted successfully',
                'event' => $event->fresh(['nameChangeRequests']),
            ]);

        } catch (\Exception $e) {
            report($e);
            \Log::error('Failed to submit name change request: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to submit name change request.',
            ], 500);
        }
    }

    /**
     * Check if the authenticated user has created events before
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function hasCreatedEvents()
    {
        return response()->json([
            'hasCreatedEvents' => auth()->user()->events()->count() > 1,
        ]);
    }

    public function duplicate(Request $request, Event $event)
    {
        // A new run of the same show (asks to join it), or an event of its own. Left out, a run, as before.
        $asRun = $request->boolean('as_run', true);

        // Check unpublished events count (bypass for admins)
        $unpublishedCount = Event::countUnpublishedEvents($event->organizer_id);

        if ($unpublishedCount >= Event::MAX_UNPUBLISHED_EVENTS && ! auth()->user()->isAdmin()) {
            return response()->json([
                'message' => 'You can only have '.Event::MAX_UNPUBLISHED_EVENTS.' unpublished events at a time.',
            ], 422);
        }

        // A new run of a live listing in no show run yet: the show run is made
        // now, from this listing, so the copy has one to join (EI's review of
        // the copy confirms it). A refusal says why and copies nothing.
        // Only when the Duplicate pop-up says so (as_run sent): other callers keep asking the listing itself.
        $starts = $request->has('as_run') && $asRun && ! $event->project_id && in_array($event->status, Event::LIVE_STATUSES, true);
        $started = null;

        try {
            // One transaction: a copy that fails leaves no show run behind.
            $newEvent = DB::transaction(function () use ($event, $asRun, $starts, $request, &$started) {
                if ($starts) {
                    $started = app(LinkEventToProject::class)->startFrom($event, $request->user());
                    $event->refresh();
                }

                return $event->duplicate($asRun);
            });

            return response()->json([
                'message' => 'Event duplicated successfully.',
                // The show run this just started, whose page opens next.
                'project' => $started?->only(['id', 'name', 'slug']),
                'event' => $newEvent->load([
                    // 'shows.tickets',
                    'location',
                    'images',
                    'advisories',
                    'mobilityAdvisories',
                    'contentAdvisories',
                    'contactLevels',
                    'interactive_level',
                    'category',
                    'genres',
                ]),
            ], 201);
        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            report($e);
            \Log::error('Failed to duplicate event: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to duplicate event.',
            ], 500);
        }
    }
}
