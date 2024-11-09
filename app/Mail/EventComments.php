<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Messaging\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventComments extends Mailable
{
    use Queueable, SerializesModels;

    public $attributes;

    public function __construct(Event $event, string $message)
    {
        $this->attributes = [
            'sender' => auth()->user()->name,
            'event' => $event->name,
            'body' => $message,
            'app_url' => config('app.url'),
            'id' => $event->id
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: 'admin@everythingimmersive.com',
            subject: 'Update About Your Event'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'Emails.message',
            with: ['attributes' => $this->attributes]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
