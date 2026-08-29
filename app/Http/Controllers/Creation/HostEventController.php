<?php

namespace App\Http\Controllers\Creation;

use App\Actions\Events\CheckDuplicateEventNames;
use App\Actions\Events\UpdateEventAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Models\Event;
use App\Services\NameChangeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HostEventController extends Controller
{
    protected $nameChangeService;

    public function __construct(NameChangeRequestService $nameChangeService)
    {
        $this->nameChangeService = $nameChangeService;
    }

    /*
     * A finished run is NOT read-only. Editing one used to 403 for anyone but
     * a moderator, on the reasoning that rewriting a past event's details
     * misrepresents what happened — but it also blocked the most ordinary
     * reason an organizer comes back to a finished event: they are running
     * the show again and want to add dates. Their only route was duplicating,
     * which starts a new listing at a new URL and abandons the original's
     * favourites, click stats and search history.
     *
     * What actually protects the record is narrower and lives deeper: Show::
     * saveShows() refuses to delete a show whose date has already passed for
     * any non-moderator, on every write path, and reports the ones it kept.
     * So the history cannot be erased, only added to — which is the real
     * invariant, and it holds without locking the whole event.
     */

    public function edit(Event $event)
    {
        $event->load([
            'shows.tickets',
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
            'nameChangeRequests' => function ($query) {  // Add this relationship
                $query->where('status', 'pending')->latest();
            },
        ]);

        return view('creation.edit', compact('event'));
    }

    public function update(StoreEventRequest $request, Event $event, UpdateEventAction $updateEvent)
    {
        $validatedData = $request->validated();

        // Check for duplicate event names if name is being updated (skip if user acknowledged)
        if (isset($validatedData['name']) && $validatedData['name'] !== $event->name && ! $request->boolean('acknowledge_duplicate')) {
            $duplicates = app(CheckDuplicateEventNames::class)->handle($validatedData['name'], $event->id);
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
                .implode(', ', $updateEvent->preservedPastDates)
                .') were kept and not removed. Only admins and moderators can remove past dates.';
        }

        // The mirror refusal — see UpdateEventAction::$rejectedPastDates.
        if (! empty($updateEvent->rejectedPastDates)) {
            $warnings[] = 'Dates in the past ('
                .implode(', ', $updateEvent->rejectedPastDates)
                .') were not added. Only admins and moderators can add dates that have already passed.';
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
        $wasPublished = in_array($event->status, ['p', 'e']);

        $event->delete();

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
        $organizerId = $request->input('organizer_id');

        // Check unpublished events count (bypass for admins)
        $unpublishedCount = Event::countUnpublishedEvents($organizerId);

        if ($unpublishedCount >= Event::MAX_UNPUBLISHED_EVENTS && ! auth()->user()->isAdmin()) {
            return response()->json([
                'message' => 'You can only have '.Event::MAX_UNPUBLISHED_EVENTS.' unpublished events at a time.',
            ], 422);
        }

        // If name is provided, check for duplicates (skip if user acknowledged)
        if ($request->has('name') && ! empty($request->name) && ! $request->boolean('acknowledge_duplicate')) {
            $duplicates = app(CheckDuplicateEventNames::class)->handle($request->name);
            if ($duplicates) {
                return response()->json([
                    'message' => 'Duplicate event name detected',
                    'duplicateEvents' => $duplicates,
                    'warning' => 'An event with a similar name already exists. This may cause confusion for attendees or be rejected during review.',
                ], 409); // 409 Conflict
            }
        }

        $event = Event::newEvent($organizerId);

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
            $request->event_id
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
        try {
            $wouldBeSlug = \Illuminate\Support\Str::slug($request->requested_name);

            // Quick check for any slug conflicts (including soft-deleted)
            $hasConflict = Event::withTrashed()
                ->where('slug', $wouldBeSlug)
                ->where('id', '!=', $event->id)
                ->exists();

            if ($hasConflict) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'requested_name' => ['An event with this name was created before. Please choose a different name or change it slightly. If you feel this is an error, please contact us at support@everythingimmersive.com'],
                    ],
                ], 422);
            }

            // Validate the request
            $validator = \Validator::make($request->all(), [
                'requested_name' => [
                    'required',
                    'string',
                    'max:100',
                    new \App\Rules\UniqueSlugRule($request->requested_name, Event::class, 'slug', $event->id),
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

    public function duplicate(Event $event)
    {
        // Check unpublished events count (bypass for admins)
        $unpublishedCount = Event::countUnpublishedEvents($event->organizer_id);

        if ($unpublishedCount >= Event::MAX_UNPUBLISHED_EVENTS && ! auth()->user()->isAdmin()) {
            return response()->json([
                'message' => 'You can only have '.Event::MAX_UNPUBLISHED_EVENTS.' unpublished events at a time.',
            ], 422);
        }

        try {
            $newEvent = $event->duplicate();

            return response()->json([
                'message' => 'Event duplicated successfully.',
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
        } catch (\Exception $e) {
            \Log::error('Failed to duplicate event: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to duplicate event.',
            ], 500);
        }
    }
}
