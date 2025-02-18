<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Comments extends Mailable
{
    use Queueable, SerializesModels;

    public $attributes;
    private $model;
    private $messageType;

    public function __construct(Model $model, string $message, string $messageType = 'update')
    {
        $this->model = $model;
        $this->messageType = $messageType;

        $this->attributes = [
            'sender' => auth()->user()->name,
            'subject' => $model->name,
            'body' => $message,
            'app_url' => config('app.url'),
            'id' => $model->id,
            'type' => $messageType,
            'title' => $this->getMessageTitle($messageType)
        ];
    }

    private function getMessageTitle(string $type): string
    {
        return match ($type) {
            'rejected' => "Important Update About {$this->model->name}",
            'approved' => "Good News About {$this->model->name}",
            'review' => "{$this->model->name} Is Being Reviewed",
            default => "New Message About {$this->model->name}"
        };
    }

    public function envelope(): Envelope
    {
        $truncatedName = Str::limit($this->model->name, 30, '...');
        
        return new Envelope(
            from: 'admin@everythingimmersive.com',
            subject: "Update About {$truncatedName}"
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