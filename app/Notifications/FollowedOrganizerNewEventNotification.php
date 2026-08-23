<?php

namespace App\Notifications;

use App\Mail\FollowedOrganizerNewEventMail;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
     * job carries every OTHER recipient's override too. Null (the default,
     * meaning the user has never touched this follow's own "Get updates"
     * toggle) means "notify" — following an organizer implies wanting to
     * hear about it. There is no separate account-wide switch layered on
     * top of this; Account Settings' "Clear all notifications" is a one-time
     * bulk action that sets every existing row's override to false, not a
     * persistent flag checked here (see ClearAllNotificationsAction).
     */
    public function __construct(public Event $event, private ?bool $notifyOverride = null) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if ($this->notifyOverride ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * A full Mailable, not a MailMessage — matches the app's own branded
     * email look (logo, red heading, event image, CTA button; see
     * emails/closing-soon.blade.php and its siblings) instead of Laravel's
     * generic notification template. MailChannel doesn't auto-address a
     * Mailable return value the way it does a MailMessage, so it's addressed
     * here.
     */
    public function toMail($notifiable): FollowedOrganizerNewEventMail
    {
        return (new FollowedOrganizerNewEventMail($this->event))->to($notifiable->email);
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
