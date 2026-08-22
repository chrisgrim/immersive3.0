<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Notifications\FollowedOrganizerNewEventNotification;
use App\Notifications\SavedEventNewDatesNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The shared "who gets notified" logic behind both Hub notification
 * triggers, called from every hook point that can produce the underlying
 * event (see UpdateEventAction for new-dates, and the embargo cron / admin
 * approval / embargo-lift-on-edit paths for followed-organizer). Centralizing
 * this means each hook point only has to say WHAT happened, not figure out
 * WHO to tell.
 */
class EventNotificationDispatcher
{
    /**
     * How often one event is allowed to re-trigger this notification — an
     * organizer adding dates across several separate edits in one day
     * should produce one email, not one per edit.
     */
    private const NEW_DATES_COOLDOWN_HOURS = 24;

    /**
     * Notifies every user who has favorited this event. Always lands in
     * each recipient's in-app feed regardless of their mail preference —
     * see SavedEventNewDatesNotification::via().
     *
     * The MAIL channel is additionally throttled to once per
     * NEW_DATES_COOLDOWN_HOURS — an organizer adding dates across several
     * separate edits in one day should produce one email, not one per edit.
     * Same atomic claim-and-skip pattern newEventFromFollowedOrganizer()
     * uses below, just on a rolling window (notified_new_dates_at resets
     * each time mail actually goes out) instead of a permanent one-time
     * flag — an organizer editing every 23 hours keeps resetting the clock
     * and never crosses the threshold, which is the intended behavior:
     * frequent edits collapse into one email per window, not zero forever.
     * The in-app feed entry is NOT throttled — it's written every time
     * regardless (mailAllowed only gates the mail channel in via()), same
     * as how the mail-preference opt-out already never hides the feed
     * entry (Chris's explicit requirement). The mail body doesn't
     * enumerate which dates were added (see
     * SavedEventNewDatesNotification::toMail()), so a throttled send loses
     * nothing worth batching — the next email after the window still
     * correctly says "new dates were added."
     */
    public function newDatesForSavedEvent(Event $event): void
    {
        // One query for every recipient's per-item override, instead of
        // each recipient's Notification::via() querying it individually.
        $overrides = $event->favorites()->pluck('notify_new_dates', 'user_id');

        if ($overrides->isEmpty()) {
            return;
        }

        $mailAllowed = Event::whereKey($event->id)
            ->where(function ($query) {
                $query->whereNull('notified_new_dates_at')
                    ->orWhere('notified_new_dates_at', '<=', now()->subHours(self::NEW_DATES_COOLDOWN_HOURS));
            })
            ->update(['notified_new_dates_at' => now()]) > 0;

        $this->notifyUsers(
            $overrides,
            fn ($override) => new SavedEventNewDatesNotification($event, $override, $mailAllowed)
        );
    }

    /**
     * Notifies every user who follows this event's organizer. Idempotent
     * across its 3 call sites (embargo cron, admin approval, embargo-lift-
     * on-edit) via an atomic conditional UPDATE on events.organizer_notified_at
     * — two of those racing for the same event (e.g. overlapping cron runs,
     * or a cron racing an admin approving the same embargoed event) would
     * otherwise both independently decide "this just published" and both
     * dispatch. Only the caller whose UPDATE actually flips the column
     * (affected rows > 0) proceeds; the loser silently no-ops.
     */
    public function newEventFromFollowedOrganizer(Event $event): void
    {
        if (! $event->organizer_id) {
            return;
        }

        $claimed = Event::whereKey($event->id)
            ->whereNull('organizer_notified_at')
            ->update(['organizer_notified_at' => now()]);

        if (! $claimed) {
            return;
        }

        // events.organizer_id has no database FK (confirmed via migration
        // audit), so a dangling id is possible — the nullsafe operator
        // avoids throwing on the lazy-loaded relation, matching how
        // FollowedOrganizerNewEventNotification::toDatabase() already
        // guards the same lookup.
        if (! $event->organizer) {
            return;
        }

        // One query for every recipient's per-item override, instead of each
        // recipient's Notification::via() querying it individually.
        $overrides = DB::table('organizer_followers')
            ->where('organizer_id', $event->organizer_id)
            ->pluck('notify_new_events', 'user_id');

        $this->notifyUsers(
            $overrides,
            fn ($override) => new FollowedOrganizerNewEventNotification($event, $override)
        );
    }

    /**
     * $overrides is keyed by user_id (from a single pluck() upstream, so
     * already unique). Builds one notification instance per recipient via
     * $makeNotification, carrying only THAT recipient's own override value —
     * not the whole map. Passing the full map into one shared, ShouldQueue
     * notification would mean every queued per-recipient job serializes
     * every OTHER recipient's override too, an O(recipients²) payload for
     * a popular event/organizer.
     */
    private function notifyUsers(Collection $overrides, callable $makeNotification): void
    {
        if ($overrides->isEmpty()) {
            return;
        }

        User::whereIn('id', $overrides->keys())->get()->each(
            fn (User $user) => $user->notify($makeNotification($overrides->get($user->id)))
        );
    }
}
