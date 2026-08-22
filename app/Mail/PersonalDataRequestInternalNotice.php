<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The internal-facing counterpart to PersonalDataRequestReceived — sent to
 * the team's contact address so a real person actually sees the request,
 * rather than the user-facing confirmation being the only record of it.
 */
class PersonalDataRequestInternalNotice extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function build()
    {
        return $this->subject("Personal data request: {$this->user->name}")
            ->text('emails.personal-data-request-internal')
            ->with([
                'name' => $this->user->name,
                'email' => $this->user->email,
                'userId' => $this->user->id,
            ]);
    }
}
