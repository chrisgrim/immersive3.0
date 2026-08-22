<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FollowedOrganizerNewEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * $notifyOverride is THIS recipient's own notify_new_events value,
     * resolved once in EventNotificationDispatcher (which queries every
     * recipient's override in one go) rather than queried per recipient
     * here. Deliberately a single value, not the full recipient map — this
     * notification is ShouldQueue, so each recipient gets their own queued
     * job/serialized payload; embedding the whole map here would mean every
     * job carries every OTHER recipient's override too. Null (the default)
     * means "no override" — via() then treats it as true (no mute), subject
     * to the account-wide gate below.
     */
    public function __construct(public Event $event, private ?bool $notifyOverride = null) {}

    /**
     * The account-wide followed_organizer_new_event setting is a master
     * switch: off means no mail for this type at all, full stop — a
     * per-follow notify_new_events override can never punch a hole through
     * that. When the account-wide setting is on, the per-follow override
     * (if set) still lets a user mute mail for one specific followed
     * organizer while leaving the rest on.
     */
    public function via($notifiable): array
    {
        $channels = ['database'];

        $globalOptIn = $notifiable->wantsNotification('followed_organizer_new_event', false);
        $wantsMail = $globalOptIn && ($this->notifyOverride ?? true);

        if ($wantsMail) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $organizerName = $this->event->organizer->name ?? 'An organizer you follow';

        return (new MailMessage)
            ->subject("New event from {$organizerName}")
            ->line("{$organizerName}, who you follow, just posted a new event: \"{$this->event->name}\".")
            ->action('View event', url("/events/{$this->event->slug}"))
            ->line("You're getting this because you follow this organizer and opted into new-event emails.");
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'followed_organizer_new_event',
            'event_id' => $this->event->id,
            'event_slug' => $this->event->slug,
            'event_name' => $this->event->name,
            'event_image' => $this->event->largeImagePath,
            'organizer_id' => $this->event->organizer_id,
            'organizer_name' => $this->event->organizer->name ?? null,
        ];
    }
}
