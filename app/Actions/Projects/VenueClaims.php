<?php

namespace App\Actions\Projects;

use App\Models\AuditLog;
use App\Models\Messaging\Message;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueClaim;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The real venue asks for its page, as a company asks for its organizer page
 * (OwnershipClaimService). Whoever added the venue keeps it until a claim is
 * approved; then the claimant's team keeps it for good (claimed_at). Every
 * step locks the venue row, so two claims or two staff clicks cannot cross.
 */
class VenueClaims
{
    public function create(Venue $venue, User $user, ?string $message): VenueClaim
    {
        $claim = DB::transaction(function () use ($venue, $user, $message) {
            $venue = Venue::whereKey($venue->id)->lockForUpdate()->first();

            if (! $venue || ! $venue->isClaimable()) {
                throw ValidationException::withMessages(['venue' => "This venue isn't available to claim."]);
            }

            if (VenueClaim::where('venue_id', $venue->id)->where('user_id', $user->id)->where('status', VenueClaim::PENDING)->exists()) {
                throw ValidationException::withMessages(['venue' => 'You already asked for this venue. We will be in touch.']);
            }

            return VenueClaim::create([
                'venue_id' => $venue->id,
                'user_id' => $user->id,
                'message' => $message,
            ]);
        });

        AuditLog::record($venue, 'venue.claimed', $user, ['claim' => $claim->id]);

        return $claim;
    }

    /** The claimant owns the venue from now on, as a claimed organizer page; other pending claims are turned down. */
    public function approve(VenueClaim $claim, User $staff): VenueClaim
    {
        [$claim, $siblings] = DB::transaction(function () use ($claim, $staff) {
            $claim = VenueClaim::whereKey($claim->id)->lockForUpdate()->first();
            if (! $claim || $claim->status !== VenueClaim::PENDING) {
                throw ValidationException::withMessages(['claim' => 'This claim has already been handled.']);
            }

            $venue = Venue::whereKey($claim->venue_id)->lockForUpdate()->first();
            if (! $venue || ! $venue->isClaimable()) {
                throw ValidationException::withMessages(['claim' => 'This venue is no longer available to claim.']);
            }

            // The person owns it now; the team that added it no longer keeps it.
            $venue->user_id = $claim->user_id;
            $venue->organizer_id = null;
            $venue->claimed_at = now();
            $venue->save();

            $this->mark($claim, VenueClaim::APPROVED, $staff);

            $siblings = VenueClaim::where('venue_id', $venue->id)->whereKeyNot($claim->id)->where('status', VenueClaim::PENDING)->get();
            foreach ($siblings as $sibling) {
                $this->mark($sibling, VenueClaim::REJECTED, $staff, 'The venue went to another claim.');
            }

            AuditLog::record($venue, 'venue.claim_approved', $staff, ['claim' => $claim->id, 'user' => $claim->user_id]);

            return [$claim, $siblings];
        });

        $this->tell($claim, 'Your claim for '.$claim->venue->name.' was approved. You can now edit its page.');
        foreach ($siblings as $sibling) {
            $this->tell($sibling, 'Your claim for '.$sibling->venue->name.' was not approved: '.$sibling->admin_notes);
        }

        return $claim;
    }

    public function reject(VenueClaim $claim, User $staff, ?string $reason): VenueClaim
    {
        $claim = DB::transaction(function () use ($claim, $staff, $reason) {
            $claim = VenueClaim::whereKey($claim->id)->lockForUpdate()->first();
            if (! $claim || $claim->status !== VenueClaim::PENDING) {
                throw ValidationException::withMessages(['claim' => 'This claim has already been handled.']);
            }

            $this->mark($claim, VenueClaim::REJECTED, $staff, $reason);

            return $claim;
        });

        $this->tell($claim, 'Your claim for '.$claim->venue->name.' was not approved'.($reason ? ': '.$reason : '.'));

        return $claim;
    }

    private function mark(VenueClaim $claim, string $status, User $staff, ?string $notes = null): void
    {
        $claim->status = $status;
        $claim->processed_by = $staff->id;
        $claim->processed_at = now();
        if ($notes !== null) {
            $claim->admin_notes = $notes;
        }
        $claim->save();
    }

    /** An inbox message to the claimant, about the venue. Never undoes the decision. */
    private function tell(VenueClaim $claim, string $text): void
    {
        try {
            $venue = $claim->venue;
            if ($venue && $claim->user_id) {
                // Message::notification writes to the model's user_id: here, the claimant.
                $venue->user_id = $claim->user_id;
                Message::notification($venue, $text, $venue->slug);
                $venue->refresh();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
