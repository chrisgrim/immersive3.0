<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PersonalDataRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    // Copied off the user rather than holding the model: this mail is queued,
    // and someone who asks for their data and then deletes their account must
    // still get (and the team must still see) the request.
    public ?string $name;

    public ?string $email;

    public int $userId;

    public function __construct(User $user)
    {
        $this->name = $user->name;
        $this->email = $user->email;
        $this->userId = $user->id;
    }

    public function build()
    {
        return $this->subject('We received your personal data request')
            ->markdown('emails.personal-data-request')
            ->with(['name' => $this->name]);
    }
}
