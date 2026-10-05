<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class Comments extends Mailable
{
    use Queueable, SerializesModels;

    public $attributes;

    private $messageType;

    // Only the name is kept, not the model: this mail is queued, and a model
    // deleted before the worker runs would fail the job on restore.
    private $modelName;

    public function __construct(Model $model, string $message, string $messageType = 'update')
    {
        $this->modelName = $model->name;
        $this->messageType = $messageType;

        $this->attributes = [
            'sender' => auth()->user()->name,
            'subject' => $model->name,
            'body' => $message,
            'app_url' => config('app.url'),
            'id' => $model->id,
            'type' => $messageType,
            'title' => $this->getMessageTitle($messageType),
        ];
    }

    private function getMessageTitle(string $type): string
    {
        return match ($type) {
            'rejected' => "Important Update About {$this->modelName}",
            'approved' => "Good News About {$this->modelName}",
            'review' => "{$this->modelName} Is Being Reviewed",
            default => "New Message About {$this->modelName}"
        };
    }

    public function envelope(): Envelope
    {
        $truncatedName = Str::limit($this->modelName, 30, '...');

        return new Envelope(
            from: 'admin@everythingimmersive.com',
            subject: "Update About {$truncatedName}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.message',
            with: ['attributes' => $this->attributes]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
