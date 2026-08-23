<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SavedEventNewDatesMail extends Mailable
{
    use Queueable, SerializesModels;

    public $event;

    public function __construct(Event $event)
    {
        $this->event = $event->load('images');
    }

    public function build()
    {
        return $this->subject("New dates added: {$this->event->name}")
            ->markdown('emails.saved-event-new-dates', [
                'event' => $this->event,
            ]);
    }
}
