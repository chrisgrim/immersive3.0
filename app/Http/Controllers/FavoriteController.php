<?php

namespace App\Http\Controllers;

use App\Actions\Events\RemoveFavoriteAction;
use App\Actions\Events\UpdateFavoriteNotifyAction;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FavoriteController extends Controller
{
    /**
     * The current user's favorited events, most-recently-favorited first, each
     * carrying its remaining-availability info. Backs the Saved Events tab.
     */
    public function index()
    {
        $user = auth()->user();
        $followedOrganizers = $this->followedOrganizers($user);

        $events = $this->favoritedEventsQuery($user)
            ->orderBy('favorites.created_at', 'desc')
            ->paginate(24)
            ->through(fn (Event $event) => $this->mapEvent($event, $followedOrganizers, $user));

        return response()->json(['events' => $events]);
    }

    /**
     * One favorited event, same shape as an index() row — backs deep-linking
     * a saved event's detail view directly (/dashboard/events/{slug}) without
     * paginating through the whole list to find it. 404s if this user hasn't
     * favorited it (matches LatestPublishedFirstScope's own status/archived gate on the
     * relation below — this endpoint isn't a general event lookup).
     */
    public function show(Event $event)
    {
        $user = auth()->user();
        $followedOrganizers = $this->followedOrganizers($user);

        $event = $this->favoritedEventsQuery($user)->whereKey($event->id)->firstOrFail();

        return response()->json(['event' => $this->mapEvent($event, $followedOrganizers, $user)]);
    }

    /**
     * Keyed by organizer id and carrying the per-follow notify override, so
     * mapEvent() below can compute the "Get updates" toggle's effective
     * per-item state without an extra query per row. One query for every
     * organizer this user follows — calling Organizer::isFollowed() per
     * event would run a fresh exists() query per row (the same appended-
     * accessor-in-a-loop N+1 class as Event::isFavorited/Category::hasEvent
     * elsewhere).
     */
    private function followedOrganizers(User $user): Collection
    {
        return $user->followedOrganizers()
            ->withPivot('notify_new_events')
            ->get()
            ->keyBy('id');
    }

    private function favoritedEventsQuery(User $user)
    {
        $now = Carbon::now();

        return $user->favouritedEvents()
            ->where('status', 'p')
            ->where('archived', false)
            ->withPivot('notify_new_dates')
            ->with([
                'organizer' => fn ($query) => $query->select('id', 'name', 'slug', 'thumbImagePath', 'created_at')->withCount('events'),
                'category', 'images', 'location', 'remotelocations',
            ])
            ->withCount(['shows as remaining_shows_count' => function ($query) use ($now) {
                $query->where('date', '>=', $now);
            }])
            ->withMin(['shows as next_show_date' => function ($query) use ($now) {
                $query->where('date', '>=', $now);
            }], 'date')
            ->withMin('shows as first_show_date', 'date')
            ->withMax('shows as last_show_date', 'date');
        // first_show_date/last_show_date back dateRangeLabel() below (Event
        // model reads them off the loaded aggregate columns, not a query).
    }

    private function mapEvent(Event $event, Collection $followedOrganizers, User $user): array
    {
        $followedOrganizer = $event->organizer ? $followedOrganizers->get($event->organizer->id) : null;

        // Effective per-item "Get updates" state. Notifications are OPT-IN:
        // only an explicit `true` counts. Null means the person saved the
        // event (or followed the organizer) without ever asking to be
        // emailed about it, and saving is not a request to be emailed —
        // same default *Notification::via() uses. One combined toggle covers
        // both event-date updates and organizer-new-event updates, so both
        // must be on for it to read as "on" — matches how toggling it sets
        // them together.
        $notifyEvent = $event->pivot->notify_new_dates ?? false;
        $notifyOrganizer = $event->organizer
            ? ($followedOrganizer->pivot->notify_new_events ?? false)
            : null;

        return [
            'id' => $event->id,
            'slug' => $event->slug,
            'name' => $event->name,
            'category' => $event->category,
            'organizer' => $event->organizer ? [
                'id' => $event->organizer->id,
                'name' => $event->organizer->name,
                'slug' => $event->organizer->slug,
                'thumbImagePath' => $event->organizer->thumbImagePath,
                'created_at' => $event->organizer->created_at,
                'events_count' => $event->organizer->events_count,
                'isFollowed' => $followedOrganizer !== null,
            ] : null,
            'largeImagePath' => $event->largeImagePath,
            'images' => $event->images,
            'price_range' => $event->price_range,
            'isFavorited' => true,
            'remaining' => $event->remainingSummary(),
            'dateRangeLabel' => $event->dateRangeLabel(),
            'runDateParts' => $event->runDateParts(),
            'hasLocation' => $event->hasLocation,
            'location_latlon' => $event->location_latlon,
            'location' => $event->location ? [
                'venue' => $event->location->venue,
                'city' => $event->location->city,
            ] : null,
            'remotelocations' => $event->remotelocations->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]),
            // (bool) is not cosmetic: the pivot column comes back as a raw
            // int, so the no-organizer branch returned 1 while the && branch
            // returned a real boolean — the same JSON field changing type
            // depending on whether the event had an organizer.
            'notifyUpdates' => (bool) ($notifyOrganizer === null ? $notifyEvent : ($notifyEvent && $notifyOrganizer)),
        ];
    }

    /**
     * Favorite the given event for the current user. Idempotent —
     * Favoritable::favorite() uses insertOrIgnore, so a repeat call (or two
     * racing tabs) never creates a duplicate row or throws. 404s for a
     * non-published event — no global scope on Event filters by status
     * (LatestPublishedFirstScope only orders), so without this check a
     * draft/embargoed/rejected event's slug could be favorited directly.
     * Same guard FollowController::store() already has
     * for organizers.
     */
    public function store(Event $event)
    {
        abort_unless($event->status === 'p', 404);

        $event->favorite();

        return response()->json(['isFavorited' => true]);
    }

    /**
     * Unfavorite the given event for the current user. Idempotent — calling this when
     * nothing exists to delete is a no-op, not an error. See RemoveFavoriteAction for
     * the organizer-unfollow-if-last-favorite cleanup this also does.
     */
    public function destroy(Event $event, RemoveFavoriteAction $action)
    {
        $action->handle($event, auth()->id());

        return response()->json(['isFavorited' => false]);
    }

    /**
     * The "Get updates" toggle on a saved event's detail page. Requires an
     * existing favorite (404s otherwise — this toggle only ever appears on
     * an already-saved event); see UpdateFavoriteNotifyAction for what it
     * actually does.
     */
    public function updateNotify(Request $request, Event $event, UpdateFavoriteNotifyAction $action)
    {
        $validated = $request->validate(['enabled' => 'required|boolean']);

        $action->handle($event, auth()->id(), $validated['enabled']);

        return response()->json(['notifyUpdates' => $validated['enabled']]);
    }
}
