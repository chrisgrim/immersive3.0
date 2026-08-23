<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FollowedOrganizerNewEventMail extends Mailable
{
    use Queueable, SerializesModels;

    public $event;

    public $organizerName;

    public function __construct(Event $event)
    {
        $this->event = $event->load('images', 'organizer');
        $this->organizerName = $event->organizer->name ?? 'An organizer you follow';
    }

    public function build()
    {
        return $this->subject("New event from {$this->organizerName}")
            ->markdown('emails.followed-organizer-new-event', [
                'event' => $this->event,
                'organizerName' => $this->organizerName,
            ]);
    }
}
