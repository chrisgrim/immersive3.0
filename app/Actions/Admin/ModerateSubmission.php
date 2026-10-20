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
     * Send a submission back with a question rather than a verdict (is this a
     * real company?). Same effect as reject(): out of the review queue, editable,
     * and back in it when the owner resubmits; only the wording differs.
     */
    public function askQuestion(Model $item, ?User $owner, string $question, string $noun, string $emailIntro): void
    {
        $item->update(['status' => 'n']);

        $this->notifyOwner(
            $item,
            $owner,
            "We have a question about your {$noun} before we can approve it.\n\nQuestion: {$question}\n\nReply here, or update your {$noun} and resubmit it.",
            "{$emailIntro}\n\nQuestion: {$question}",
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
