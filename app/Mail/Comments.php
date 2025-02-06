<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;

class Comments extends Mailable
{
    use Queueable, SerializesModels;

    public $attributes;

    public function __construct(Model $model, string $message)
    {
        $this->attributes = [
            'sender' => auth()->user()->name,
            'subject' => $model->name,
            'body' => $message,
            'app_url' => config('app.url'),
            'id' => $model->id
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: 'admin@everythingimmersive.com',
            subject: 'Update About Your Content'
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