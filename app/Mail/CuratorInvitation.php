<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CuratorInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $community;
    public $invitation;

    public function __construct($community, $invitation)
    {
        $this->community = $community;
        $this->invitation = $invitation;
    }

    public function build()
    {
        return $this->markdown('emails.curator-invitation')
                    ->subject("Invitation to curate {$this->community->name}");
    }
}