<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginCode extends Mailable
{
    use Queueable, SerializesModels;

    public $code;

    /**
     * Create a new message instance.
     */
    public function __construct($code)
    {
        $this->code = $code;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject("Your one-time access code: {$this->code}")
                   ->markdown('emails.login-code')
                   ->with([
                       'code' => $this->code,
                       'email' => $this->to[0]['address']
                   ]);
    }
}