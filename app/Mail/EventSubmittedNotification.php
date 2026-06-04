<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventSubmittedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $eventName;

    public $eventSlug;

    public $organizerName;

    // Pull primitives in the constructor (avoid lazy-loaded relations + the reserved
    // mail-view variables like $message at render time).
    public function __construct(Event $event)
    {
        $event->loadMissing('organizer');

        $this->eventName = $event->name;
        $this->eventSlug = $event->slug;
        $this->organizerName = $event->organizer?->name;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New event submitted - {$this->eventName}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.event-submitted');
    }

    public function attachments(): array
    {
        return [];
    }
}
