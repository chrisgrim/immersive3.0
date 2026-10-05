<?php

namespace App\Actions\Admin;

use App\Mail\Comments;
use App\Models\Messaging\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

/**
 * The moderation steps events, organizers and communities share, so the
 * three admin controllers cannot drift apart again (they were copies, and
 * already disagreed on wording and on how they found the owner).
 */
class ModerateSubmission
{
    /** Validation for a rejection's reason, the same for every kind. */
    public const REASON_RULES = ['reason' => 'required|string|max:1000'];

    /**
     * Send a submission back with the moderator's reason, and tell its owner.
     *
     * @param  string  $noun  what it is, in the owner's message ("event", "organizer", "community")
     * @param  string  $emailIntro  the Message::MESSAGES line the email opens with
     */
    public function reject(Model $item, ?User $owner, string $reason, string $noun, string $emailIntro): void
    {
        // The reason lives in the owner's message thread; no table has a
        // rejection_reason column (the copies this replaced "saved" one, and
        // mass assignment silently dropped it).
        $item->update(['status' => 'n']);

        $this->notifyOwner(
            $item,
            $owner,
            "We've reviewed your {$noun} and have some feedback that needs to be addressed.\n\nReason: {$reason}",
            "{$emailIntro}\n\nReason: {$reason}",
            'rejected',
        );
    }

    /**
     * An in-app message and an email to the owner, unless the moderator
     * acting is the owner (or there is no owner to tell).
     */
    public function notifyOwner(Model $item, ?User $owner, string $inAppMessage, string $emailMessage, string $type): void
    {
        if (! $owner || auth()->id() === $owner->id) {
            return;
        }

        Message::notification($item, $inAppMessage, $item->slug);
        // Queued: the status has already changed, so a mail-server hiccup must
        // not turn the moderator's click into an error.
        Mail::to($owner)->queue(new Comments($item, $emailMessage, $type));
    }
}
