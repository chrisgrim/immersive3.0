<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "A passkey was added to / removed from your account", so someone who did
 * not do it hears about it. Takes the passkey's name, not the model: a
 * removed passkey is already gone by the time the queue sends this.
 */
class PasskeyChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const ADDED = 'added';

    public const REMOVED = 'removed';

    public function __construct(public string $change, public string $passkeyName) {}

    public function build()
    {
        $subject = $this->change === self::ADDED
            ? 'A passkey was added to your account'
            : 'A passkey was removed from your account';

        return $this->subject($subject)
            ->view('emails.passkey-changed', [
                'subject' => $subject,
                'change' => $this->change,
                'passkeyName' => $this->passkeyName,
                'settingsUrl' => config('app.url').'/account-settings/login-security',
            ]);
    }
}
