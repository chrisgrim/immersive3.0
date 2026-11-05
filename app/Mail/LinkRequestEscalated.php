<?php

namespace App\Mail;

use App\Models\ProjectLinkRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LinkRequestEscalated extends Mailable
{
    use Queueable, SerializesModels;

    public $eventName;

    public $showName;

    public $organizerName;

    // Plain values, so it queues cleanly (as EventSubmittedNotification).
    public function __construct(ProjectLinkRequest $request)
    {
        $event = $request->event;

        $this->eventName = $event?->name;
        $this->showName = $request->project?->name ?? $request->sourceEvent?->name;
        $this->organizerName = $event?->organizer?->name;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Link request waiting 72 hours - {$this->eventName}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.link-request-escalated');
    }

    public function attachments(): array
    {
        return [];
    }
}
