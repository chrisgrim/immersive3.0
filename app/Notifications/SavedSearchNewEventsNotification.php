<?php

namespace App\Notifications;

use App\Actions\Search\BuildSearchUrlAction;
use App\Mail\SavedSearchNewEventsMail;
use App\Models\SavedSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The saved-search "notify me about new events" feature (moderators/admins
 * only for now — see User::isModerator() and NotifySavedSearchMatchesCommand,
 * which dispatches this). Unlike
 * SavedEventNewDatesNotification/FollowedOrganizerNewEventNotification,
 * there's no per-item override resolved once at dispatch time — instead
 * via() re-reads the saved search fresh from the DB every time it runs
 * (see its own comment for why: this is ShouldQueue, so dispatch and
 * delivery are two different moments in time).
 */
class SavedSearchNewEventsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $backoff = [60, 300, 900];

    /**
     * @param  Collection<int, \App\Models\Event>  $events  Already capped by
     *                                                      the command (50 — see its own comment) before construction, so
     *                                                      this never serializes an unbounded queue payload.
     */
    public function __construct(public SavedSearch $savedSearch, public Collection $events) {}

    /**
     * Re-fetches the saved search rather than trusting $this->savedSearch
     * (a snapshot frozen at dispatch/construct time, since this class isn't
     * SerializesModels) — a queued job's via() runs whenever the worker
     * actually processes it, which can be seconds or minutes after
     * NotifySavedSearchMatchesCommand dispatched it. Without this re-check,
     * a user who disables the toggle (or hits Clear All, which flips the
     * same column — see ClearAllNotificationsAction) in that window would
     * still get the email the command already queued before they opted
     * out. A deleted search (soft- or hard-deleted) is treated the same as
     * disabled — no row means nothing to notify about anymore.
     */
    public function via($notifiable): array
    {
        $current = SavedSearch::find($this->savedSearch->id);

        if (! $current || ! $current->notify_new_events) {
            return [];
        }

        return ['database', 'mail'];
    }

    public function toMail($notifiable): SavedSearchNewEventsMail
    {
        return (new SavedSearchNewEventsMail($this->savedSearch, $this->events))->to($notifiable->email);
    }

    /**
     * Unlike the other two notification types, this one isn't about a
     * single event — it's a match SET, so there's no one event_slug to
     * link to. Notifications/index.vue's feed link/message need their own
     * type-specific branch for 'saved_search_new_events' (reading `url`/
     * `saved_search_name`/`event_count` below) rather than reusing the
     * single-event event_slug/event_name fields the other two types rely
     * on (Codex caught this in review — the feed previously linked to
     * `/events/undefined` and showed a generic fallback message for this
     * type).
     */
    public function toDatabase($notifiable): array
    {
        $first = $this->events->first();

        return [
            'type' => 'saved_search_new_events',
            'saved_search_id' => $this->savedSearch->id,
            'saved_search_name' => $this->savedSearch->name,
            'event_count' => $this->events->count(),
            // Where the feed row links to — the search's own replay URL
            // (same one the email's "View All Matches" button uses), not a
            // single event page.
            'url' => (new BuildSearchUrlAction)->handle($this->savedSearch->criteria),
            // Representative thumbnail for the feed row — same key name
            // ('event_image') the other two notification types already use,
            // so the image-rendering markup itself needs no branching.
            'event_image' => $first?->largeImagePath,
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[notifications] saved_search_new_events permanently failed', [
            'saved_search_id' => $this->savedSearch->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
