<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\Events\ContentAdvisory;
use App\Models\Events\MobilityAdvisory;
use App\Models\Events\RemoteLocation;
use App\Models\Events\Show;
use App\Models\Events\Ticket;
use App\Models\Genre;
use App\Services\EventNotificationDispatcher;
use App\Services\ImageHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single write path for partial event updates.
 *
 * Extracted verbatim from HostEventController@update so the web wizard and the
 * MCP tools share one battle-tested implementation. The $request parameter
 * carries the pieces the web flow sends outside the validated payload (file
 * uploads, JSON-encoded currentImages/deletedImages/videos); MCP callers pass
 * a synthesized Request whose file/JSON branches simply never trigger.
 *
 * The internal ordering matters: shows are saved before tickets because
 * Ticket::handleTickets binds tickets to the event's existing shows.
 */
class UpdateEventAction
{
    /**
     * Populated by handle() if a non-staff caller's schedule change would
     * have removed a show that's already happened — Show::saveShows() kept
     * it instead. Callers (HostEventController::update(), the MCP
     * UpdateEvent tool) read this after calling handle() to tell the editor
     * why the saved schedule doesn't exactly match what they submitted,
     * rather than reporting a silent, unqualified success.
     *
     * @var array<int, string>
     */
    public array $preservedPastDates = [];

    /**
     * Dates in the past the caller asked to CREATE and did not get.
     *
     * The mirror of $preservedPastDates: Show::saveShows() refuses to invent a
     * show that already happened for a non-moderator, just as it refuses to
     * erase one. Reported for the same reason — the saved schedule does not
     * match what was submitted, and saying so beats an unqualified success.
     *
     * @var array<int, string>
     */
    public array $rejectedPastDates = [];

    /**
     * Whether a requested embargo on a published event was refused.
     *
     * Show::applyEmbargo() will not move a published event whose run has
     * already ended into embargo for a non-moderator (lifting it again is
     * how an event announces itself to the organizer's followers), and it
     * does not store the date either. Reported for the same reason as the
     * two lists above: the save did not do what was asked.
     */
    public bool $embargoRefused = false;

    public function handle(Event $event, array $validatedData, Request $request): Event
    {
        $wasPublished = in_array($event->status, ['p', 'e']);
        $oldStatus = $event->status;  // Store original status
        $oldCategoryId = $event->category_id;
        // Both the mass-assign below and Show::saveShows write the new show type
        // onto $event, so the only place the previous one still exists is here.
        // Show::updateEvent needs it to tell a real type switch from a bare echo.
        $oldShowtype = $event->showtype;

        // Guard the showtype/shows invariant: switching INTO a specific/ongoing
        // type with no dates would flip showtype (mass-assigned below) while the
        // old shows survive, leaving type and shows describing different
        // schedules. Drop the showtype in that case so it is neither mass-assigned
        // nor applied via saveShows/updateEvent. A same-type echo is left alone
        // (a legitimate no-op, e.g. a show_times-only edit). The MCP tool already
        // rejects this with an error; this protects any other caller of the shared
        // save path, such as a direct form POST.
        if (isset($validatedData['showtype'])
            && in_array($validatedData['showtype'], ['s', 'o'], true)
            && empty($validatedData['dateArray'] ?? [])
            && $validatedData['showtype'] !== $event->showtype) {
            unset($validatedData['showtype']);
        }

        // Handle attendance type changes (using either hasLocation or attendance_type_id)
        if (isset($validatedData['attendance_type_id']) && $event->category) {
            // Check if category is compatible with the attendance type
            if (! $event->category->supportsAttendanceType($validatedData['attendance_type_id'])) {
                $event->category()->dissociate();
                $validatedData['status'] = '1';
            }
            // Keep hasLocation in sync for backward compatibility
            $validatedData['hasLocation'] = $validatedData['attendance_type_id'] == 1;
        }
        // Legacy handling for hasLocation
        elseif (isset($validatedData['hasLocation']) && $event->category &&
            $event->category->remote === $validatedData['hasLocation']) {
            $event->category()->dissociate();
            $validatedData['status'] = '1';
            // Set the corresponding attendance_type_id
            $validatedData['attendance_type_id'] = $validatedData['hasLocation'] ? 1 : 2;
        }

        // Handle location updates
        if (isset($validatedData['location'])) {
            $event->location->update($validatedData['location']);

            // Update the location_latlon in events table using the exact format
            if ($event->location->latitude && $event->location->longitude) {
                $event->update([
                    'location_latlon' => [
                        'lat' => (float) $event->location->latitude,
                        'lon' => (float) $event->location->longitude,
                    ],
                ]);
            }

            // Update the status if it's included in the request
            if (isset($validatedData['status'])) {
                $event->status = $validatedData['status'];
            }

            $event->save();
        } else {
            $event->update($validatedData);
        }

        if (isset($validatedData['remotelocations'])) {
            $this->storeRemoteLocations($validatedData['remotelocations'], $event);
        }

        // Handle timezone directly if provided
        if (isset($validatedData['timezone'])) {
            $event->timezone = $validatedData['timezone'];
            $event->save();
        }

        if (isset($validatedData['showtype'])) {
            DB::transaction(function () use ($event, $request, $oldShowtype) {
                // Serializes concurrent editors of the same event's
                // schedule — without this, two near-simultaneous saves
                // (double submit, two editors) could both read the same
                // "before" snapshot below and both decide the same new
                // dates were just added, double-notifying every favoriter.
                // See notifyIfNewDatesAdded's docblock.
                Event::whereKey($event->id)->lockForUpdate()->first();

                // Captured before the write so it reflects the schedule
                // favoriters actually saved the event under, not the new one.
                $datesBeforeUpdate = $this->scheduleDays($event);

                $showResult = Show::saveShows($request, $event, $oldShowtype);
                $this->preservedPastDates = $showResult['preserved'];
                $this->rejectedPastDates = $showResult['rejected'];
                Show::updateEvent($request, $event, $oldShowtype);

                // After the schedule, so the guard sees the closing date this
                // save produced; before the notification, which only goes out
                // for a published event.
                $this->embargoRefused = Show::applyEmbargo($request, $event);

                $this->notifyIfNewDatesAdded($event, $datesBeforeUpdate);
            });
        } else {
            // An embargo sent without a schedule — see Show::applyEmbargo.
            $this->embargoRefused = Show::applyEmbargo($request, $event);
        }

        // Handle all advisory-related updates
        if (isset($validatedData['contentAdvisories'])) {
            ContentAdvisory::saveAdvisories($event, $validatedData['contentAdvisories']);
        }

        if (isset($validatedData['mobilityAdvisories'])) {
            MobilityAdvisory::saveAdvisories($event, $validatedData['mobilityAdvisories']);
        }

        // Consolidate all advisory updates
        $advisoryData = [];

        if (isset($validatedData['advisories'])) {
            if (isset($validatedData['advisories']['sexual'])) {
                $advisoryData['sexual'] = (bool) $validatedData['advisories']['sexual'];
            }

            if (isset($validatedData['advisories']['sexualDescription'])) {
                $advisoryData['sexualDescription'] = $validatedData['advisories']['sexualDescription'];
            }

            if (isset($validatedData['advisories']['audience'])) {
                $advisoryData['audience'] = $validatedData['advisories']['audience'];
            }
        }

        // Add wheelchair status to advisory data
        if (isset($validatedData['wheelchairReady'])) {
            $advisoryData['wheelchairReady'] = $validatedData['wheelchairReady'];
        }

        if (! empty($advisoryData)) {
            $event->advisories()->update($advisoryData);
        }

        if (isset($validatedData['tickets'])) {
            Ticket::handleTickets($request, $event);
        }

        if ($request->has('currentImages') || $request->has('deletedImages') || $request->hasFile('images')) {
            // 1. First, get all current images and their ranks
            $existingImages = $event->images()->orderBy('rank')->get();

            // 2. Handle deletions first
            if ($request->has('deletedImages')) {
                $deletedImages = json_decode($request->input('deletedImages', '[]'), true);
                foreach ($deletedImages as $deletedImagePath) {
                    $image = $existingImages->first(function ($img) use ($deletedImagePath) {
                        return $img->large_image_path === $deletedImagePath;
                    });
                    if ($image) {
                        ImageHandler::deleteImage($image);
                        $existingImages = $existingImages->reject(fn ($img) => $img->id === $image->id);
                    }
                }
            }

            // 3. Handle reordering of existing images
            if ($request->has('currentImages')) {
                $currentImages = json_decode($request->input('currentImages'), true);

                if ($currentImages && count($currentImages) > 0) {
                    // Update ranks of existing images
                    foreach ($currentImages as $image) {
                        // Skip if no ID - new uploads are handled separately
                        if (! isset($image['id'])) {
                            continue;
                        }

                        $eventImage = $event->images()->find($image['id']);
                        if ($eventImage && isset($image['rank'])) {
                            $eventImage->rank = (int) $image['rank'];
                            $eventImage->save();
                        }
                    }
                }
            }

            // 4. Handle new image uploads
            if ($request->hasFile('images')) {
                $ranks = $request->input('ranks', []);

                foreach ($request->file('images') as $index => $image) {
                    $rank = (int) ($ranks[$index] ?? 0);

                    // First delete any existing image with this rank
                    $existingImage = $event->images()->where('rank', $rank)->first();
                    if ($existingImage) {
                        ImageHandler::deleteImage($existingImage);
                    }

                    // Save the new image with the correct rank
                    ImageHandler::saveImage(
                        $image,
                        $event,
                        ($rank === 0) ? 900 : 1200,  // Width
                        ($rank === 0) ? 1200 : 800,  // Height
                        'event-images',
                        $rank
                    );
                }

                // Refresh to get the latest state
                $event->refresh();
            }
        }

        // Handle Contact Level
        if (isset($validatedData['contactLevel'])) {
            $event->contactLevels()->sync([$validatedData['contactLevel']['id']]);
        }

        // Handle Interactive Level
        if (isset($validatedData['interactiveLevel'])) {
            $event->interactive_level_id = $validatedData['interactiveLevel']['id'];
            $event->save();
        }

        // Handle Age Limit
        if (isset($validatedData['ageLimit'])) {
            $event->age_limits_id = $validatedData['ageLimit']['id'];
            $event->save();
        }

        // Handle videos
        if ($request->has('videos')) {
            $videosData = json_decode($request->input('videos'), true);

            // Delete existing videos
            $event->videos()->delete();

            // Create new videos with the provided data
            foreach ($videosData as $videoData) {
                $event->videos()->create([
                    'platform' => $videoData['platform'],
                    'url' => $videoData['url'],
                    'rank' => $videoData['rank'] ?? 0,
                    // If 'id' in videoData is the platform's video ID (e.g., YouTube ID)
                    // it should not be confused with the database ID
                    'platform_video_id' => $videoData['id'] ?? null,
                ]);
            }

            // Handle video slideshow preference
            if ($request->has('videoSlideshow')) {
                $event->video = $request->videoSlideshow ?: null;
                $event->save();
            }
        }

        // Handle call to action text
        if ($request->has('call_to_action')) {
            $event->call_to_action = $request->call_to_action;
            $event->save();
        }

        // Handle genres
        if (isset($validatedData['genres'])) {
            Genre::saveGenres($event, $validatedData['genres']);

            if ($wasPublished || in_array($event->status, ['p', 'e'])) {
                Cache::forget('active-genres');
            }
        }

        // Check if category changed
        if ($oldCategoryId !== $event->category_id) {
            if ($wasPublished || in_array($event->status, ['p', 'e'])) {
                Cache::forget('active-categories');
            }
        }

        // After any update that might change status
        if ($oldStatus === 'e' && $event->status === 'p') {
            Cache::forget('active-categories');
            Cache::forget('active-genres');

            // Third hook point for the followed-organizer trigger (the other
            // two are the embargo cron and admin approval) — an edit that
            // lifts the embargo directly (e.g. removing the embargo date)
            // makes the event live outside either of those paths.
            app(EventNotificationDispatcher::class)->newEventFromFollowedOrganizer($event);
        }

        return $event;
    }

    /**
     * Relations an event editor (web wizard or MCP client) needs to see the
     * full editable state after a write.
     */
    public static function editorRelations(): array
    {
        return [
            'shows.tickets',
            'location',
            'images',
            'advisories',
            'mobilityAdvisories',
            'contentAdvisories',
            'contactLevels',
            'interactive_level',
            'category',
            'genres',
            'videos',
            'age_limits',
            'nameChangeRequests' => function ($query) {
                $query->where('status', 'pending')->latest();
            },
        ];
    }

    protected function storeRemoteLocations($remoteLocations, Event $event): void
    {
        foreach ($remoteLocations as $loc) {
            RemoteLocation::firstOrCreate(
                ['slug' => Str::slug($loc['name'])],
                [
                    'name' => $loc['name'],
                    'user_id' => auth()->user()->id,
                ]
            );
        }

        $newSync = RemoteLocation::whereIn('slug', collect($remoteLocations)->map(function ($item) {
            return Str::slug($item['name']);
        })->toArray())->get();

        $event->remotelocations()->sync($newSync);
    }

    /**
     * Only published events can have favoriters in the first place (the
     * favorite UI only ever appears on a published event), so a draft/
     * embargoed event's date changes never need to notify anyone here.
     */
    private function notifyIfNewDatesAdded(Event $event, array $datesBeforeUpdate): void
    {
        if ($event->status !== 'p') {
            return;
        }

        $datesAfterUpdate = $this->scheduleDays($event);
        $addedDates = array_diff($datesAfterUpdate, $datesBeforeUpdate);

        if (empty($addedDates)) {
            return;
        }

        app(EventNotificationDispatcher::class)->newDatesForSavedEvent($event);
    }

    /**
     * The schedule as the calendar days it plays, in the event's timezone.
     * "New dates" means new DAYS: Show::saveShows() moves a row whose
     * stored time is off the noon convention onto it in place, and a raw
     * datetime comparison read that move as a date being added — and would
     * have told every favoriter so.
     *
     * @return string[]
     */
    private function scheduleDays(Event $event): array
    {
        $tz = Show::validTimezone($event->timezone);
        $rows = $event->shows()->pluck('date');
        $curtainTimes = Show::usesCurtainTimes($rows);

        return $rows
            ->map(fn ($d) => Show::localDay($d, $tz, $curtainTimes))
            ->unique()
            ->values()
            ->all();
    }
}
