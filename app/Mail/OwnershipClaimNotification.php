<?php

namespace App\Mail;

use App\Models\OwnershipClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnershipClaimNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $organizerName;

    public $claimantName;

    public $claimantEmail;

    // NB: not `$message` — that name collides with the Illuminate\Mail\Message instance
    // Laravel injects into every mail view, which would shadow this and break rendering.
    public $claimMessage;

    public $adminNotes;

    public $status;

    public $isAdmin;

    /**
     * Pull primitive values off the claim in the constructor (mirroring NameChangeNotification)
     * so the mailable doesn't depend on lazy-loaded relations at render time.
     */
    public function __construct(OwnershipClaim $claim, bool $isAdmin = true)
    {
        $claim->loadMissing(['organizer', 'user']);

        $this->organizerName = $claim->organizer?->name ?? 'an organization';
        $this->claimantName = $claim->user?->name;
        $this->claimantEmail = $claim->user?->email;
        $this->claimMessage = $claim->message;
        $this->adminNotes = $claim->admin_notes;
        $this->status = $claim->status;
        $this->isAdmin = $isAdmin;
    }

    public function envelope(): Envelope
    {
        if ($this->isAdmin) {
            $subject = "Ownership Claim - {$this->organizerName}";
        } elseif ($this->status === 'approved') {
            $subject = "You're now the owner of {$this->organizerName}";
        } else {
            $subject = "Your ownership claim for {$this->organizerName}";
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ownership-claim',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
