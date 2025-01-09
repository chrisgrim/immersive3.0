<?php

namespace App\Mail;

use App\Models\NameChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NameChangeNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $currentName;
    public $newName;
    public $reason;
    public $isAdmin;
    public $type;
    public $status;

    /**
     * Create a new message instance.
     */
    public function __construct($model, bool $isAdmin = true)
    {
        if ($model instanceof NameChangeRequest) {
            $this->currentName = $model->current_name;
            $this->newName = $model->requested_name;
            $this->reason = $model->reason;
            $this->type = class_basename($model->requestable_type);
            $this->status = $model->status;
        } else {
            $this->currentName = $model->original_name;
            $this->newName = $model->name;
            $this->reason = null;
            $this->type = $model->type;
            $this->status = 'approved';
        }
        
        $this->isAdmin = $isAdmin;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->isAdmin 
            ? "Name Change Request - {$this->currentName}"
            : "{$this->type} Name Change Request - " . ($this->status === 'rejected' ? 'Not Approved' : 'Approved');

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.name-change-notification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
