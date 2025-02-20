<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Event;
use Illuminate\Support\Str;

class EventClosingSoon extends Mailable
{
    use Queueable, SerializesModels;

    public $event;
    public $user;

    public function __construct(Event $event)
    {
        $this->event = $event->load(['images', 'organizer.user']);
        $this->user = $event->organizer->user;
    }

    public function build()
    {
        $truncatedName = Str::limit($this->event->name, 40, '...');
        return $this->subject('Your Event Is Closing Soon - ' . $truncatedName)
            ->markdown('Emails.closing-soon', [
                'event' => $this->event,
                'user' => $this->user
            ]);
    }
}
