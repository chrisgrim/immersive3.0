<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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
     * job carries every OTHER recipient's override too. Null (the default)
     * means "no override" — via() then treats it as true (no mute), subject
     * to the account-wide gate below.
     */
    /**
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
     * gated by either (Chris's explicit requirement).
     *
     * The account-wide saved_event_new_dates setting is a master switch: off
     * means no mail for this type at all, full stop — a per-favorite
     * notify_new_dates override can never punch a hole through that. When
     * the account-wide setting is on, the per-favorite override (if set)
     * still lets a user mute mail for one specific saved event while
     * leaving the rest on. $mailAllowed then applies on top of both: an
     * event that already emailed everyone about new dates within the
     * cooldown window skips mail again even for a fully opted-in recipient.
     */
    public function via($notifiable): array
    {
        $channels = ['database'];

        $globalOptIn = $notifiable->wantsNotification('saved_event_new_dates', false);
        $wantsMail = $globalOptIn && ($this->notifyOverride ?? true) && $this->mailAllowed;

        if ($wantsMail) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New dates added: {$this->event->name}")
            ->line("An event you saved, \"{$this->event->name}\", just added new dates.")
            ->action('View event', url("/events/{$this->event->slug}"))
            ->line("You're getting this because you saved this event and opted into date-update emails.");
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
