<?php

namespace App\Notifications;

use App\Mail\SavedEventNewDatesMail;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SavedEventNewDatesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * $notifyOverride is THIS recipient's own notify_new_dates value,
     * resolved once in EventNotificationDispatcher (which queries every
     * recipient's override in one go) rather than queried per recipient
     * here. Deliberately a single value, not the full recipient map — this
     * notification is ShouldQueue, so each recipient gets their own queued
     * job/serialized payload; embedding the whole map here would mean every
     * job carries every OTHER recipient's override too. Null (the default,
     * meaning the user has never touched this favorite's own "Get updates"
     * toggle) means "notify" — saving an event implies wanting to hear about
     * it. There is no separate account-wide switch layered on top of this;
     * Account Settings' "Clear all notifications" is a one-time bulk action
     * that sets every existing row's override to false, not a persistent
     * flag checked here (see ClearAllNotificationsAction).
     *
     * $mailAllowed is EventNotificationDispatcher's per-event mail-frequency
     * throttle (see newDatesForSavedEvent) — one shared value for every
     * recipient of a given dispatch, unlike $notifyOverride which is
     * per-recipient. Defaults true so directly constructing this
     * notification elsewhere (e.g. a test) doesn't need to think about
     * throttling unless it wants to.
     */
    public function __construct(
        public Event $event,
        private ?bool $notifyOverride = null,
        private bool $mailAllowed = true,
    ) {}

    /**
     * Always lands in the in-app feed (database) regardless of mail
     * preference OR the mail-frequency throttle — only the mail channel is
     * gated by either (Chris's explicit requirement). $mailAllowed applies
     * on top of the per-favorite override: an event that already emailed
     * everyone about new dates within the cooldown window skips mail again
     * even for a recipient who wants updates.
     */
    public function via($notifiable): array
    {
        $channels = ['database'];

        if (($this->notifyOverride ?? true) && $this->mailAllowed) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * A full Mailable, not a MailMessage — see the matching comment on
     * FollowedOrganizerNewEventNotification::toMail() for why.
     */
    public function toMail($notifiable): SavedEventNewDatesMail
    {
        return (new SavedEventNewDatesMail($this->event))->to($notifiable->email);
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'saved_event_new_dates',
            'event_id' => $this->event->id,
            'event_slug' => $this->event->slug,
            'event_name' => $this->event->name,
            'event_image' => $this->event->largeImagePath,
        ];
    }
}
