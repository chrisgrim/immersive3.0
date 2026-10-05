<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CuratorInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $communityName;
    public $imagePath;
    public $token;

    // Plain values, not models: the mail is queued, and a community deleted
    // before the worker runs (its invitations cascade) would fail the job.
    public function __construct($community, $invitation)
    {
        $this->communityName = $community->name;
        $this->imagePath = ltrim((string) ($community->images?->first()?->large_image_path ?? $community->largeImagePath), '/');
        $this->token = $invitation->token;
    }

    public function build()
    {
        return $this->markdown('emails.curator-invitation')
                    ->subject("Invitation to curate {$this->communityName}");
    }
}
