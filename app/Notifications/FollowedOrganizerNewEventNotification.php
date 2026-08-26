<?php

namespace App\Notifications;

use App\Mail\FollowedOrganizerNewEventMail;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class FollowedOrganizerNewEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Escalating gaps between the worker's 3 retry attempts (see
     * `--tries=3` on ei-queue.service/ei-queue-dev.service) — a mail
     * provider outage or transient SMTP error isn't fixed by hammering it
     * again a second later. 1 minute, then 5, then 15.
     */
    public array $backoff = [60, 300, 900];

    /**
     * $notifyOverride is THIS recipient's own notify_new_events value,
     * resolved once in EventNotificationDispatcher (which queries every
     * recipient's override in one go) rather than queried per recipient
     * here. Deliberately a single value, not the full recipient map — this
     * notification is ShouldQueue, so each recipient gets their own queued
     * job/serialized payload; embedding the whole map here would mean every
     * job carries every OTHER recipient's override too. Null (the default,
     * meaning the user has never touched this follow's own "Get updates"
     * toggle) means "do NOT email" — these notifications are opt-in, and
     * following an organizer is not by itself a request to be emailed.
     * There is no separate account-wide switch layered on
     * top of this; Account Settings' "Clear all notifications" is a one-time
     * bulk action that sets every existing row's override to false, not a
     * persistent flag checked here (see ClearAllNotificationsAction).
     */
    public function __construct(public Event $event, private ?bool $notifyOverride = null) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if ($this->notifyOverride ?? false) {
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

    /**
     * Runs once all 3 tries are exhausted — the job itself already landed in
     * failed_jobs by this point (Laravel's default behavior), this just adds
     * the context (which event/recipient) that a bare failed_jobs row on its
     * own doesn't make obvious at a glance.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('[notifications] followed_organizer_new_event permanently failed', [
            'event_id' => $this->event->id,
            'event_slug' => $this->event->slug,
            'exception' => $exception->getMessage(),
        ]);
    }
}
