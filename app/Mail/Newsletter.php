<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class Newsletter extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The events to feature in this week's newsletter.
     */
    public Collection $events;

    public function __construct(Collection $events)
    {
        $this->events = $events;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: 'admin@everythingimmersive.com',
            subject: 'Everything Immersive — This Week',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter',
            with: ['events' => $this->events],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
