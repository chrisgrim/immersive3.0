<?php

namespace App\Mail;

use App\Models\Organizer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizerSubmittedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $organizerName;

    public $organizerSlug;

    public $submittedByName;

    public $submittedByEmail;

    // Pull primitives in the constructor (avoid lazy-loaded relations + the reserved
    // mail-view variables like $message at render time).
    public function __construct(Organizer $organizer)
    {
        $organizer->loadMissing('owner');

        $this->organizerName = $organizer->name;
        $this->organizerSlug = $organizer->slug;
        $this->submittedByName = $organizer->owner?->name;
        $this->submittedByEmail = $organizer->owner?->email;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New organizer submitted - {$this->organizerName}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.organizer-submitted');
    }

    public function attachments(): array
    {
        return [];
    }
}
