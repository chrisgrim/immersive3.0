<?php

namespace App\Services;

use App\Mail\OwnershipClaimNotification;
use App\Models\Event;
use App\Models\Messaging\Message;
use App\Models\Organizer;
use App\Models\OwnershipClaim;
use App\Models\User;
use App\Scopes\PublishedScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OwnershipClaimService
{
    /**
     * Create an ownership-claim request for an organizer. Returns a result array the
     * controller maps to a JSON response: ['success' => bool, 'message' => string, 'status' => int].
     */
    public function create(Organizer $organizer, User $user, ?string $message = null): array
    {
        // Run eligibility + insert behind a lock on the organizer row so the checks can't go
        // stale against a concurrent transfer/team-attach, and a double-submit can't create
        // two pending claims (and two admin emails) for the same user.
        $result = DB::transaction(function () use ($organizer, $user, $message) {
            $organizer = Organizer::whereKey($organizer->id)->lockForUpdate()->first();

            if (! $organizer || ! $organizer->isClaimable()) {
                return ['success' => false, 'message' => "This organization isn't available to claim.", 'status' => 422];
            }

            if ($user->belongsToOrganization($organizer)) {
                return ['success' => false, 'message' => 'You already belong to this organization.', 'status' => 422];
            }

            if ($organizer->ownershipClaims()->where('user_id', $user->id)->where('status', 'pending')->exists()) {
                return ['success' => false, 'message' => 'You already have a pending claim for this organization.', 'status' => 422];
            }

            // organizer_id is set by the relationship; user_id + message are the only user-supplied
            // fields. status defaults to 'pending' at the DB level.
            $claim = $organizer->ownershipClaims()->create([
                'user_id' => $user->id,
                'message' => $message,
            ]);

            return ['success' => true, 'message' => 'Ownership request submitted for review.', 'status' => 201, 'claim' => $claim];
        });

        if ($result['success']) {
            $this->notifyAdmins($result['claim']);
        }

        return $result;
    }

    /**
     * Approve a claim: transfer full ownership of the organizer (and its events) to the
     * claimant, auto-reject any sibling claims, and notify everyone — all DB writes inside
     * a row-locked transaction, all mail/in-app notifications after commit.
     */
    public function approve(OwnershipClaim $claim, User $admin): array
    {
        $result = DB::transaction(function () use ($claim, $admin) {
            // Lock the claim row so two admins can't both process it.
            $claim = OwnershipClaim::whereKey($claim->id)->lockForUpdate()->first();

            if (! $claim || $claim->status !== 'pending') {
                return ['success' => false, 'message' => 'This claim has already been processed.', 'status' => 422];
            }

            // Lock the organizer too so concurrent approvals serialize.
            $organizer = Organizer::whereKey($claim->organizer_id)->lockForUpdate()->first();

            if (! $organizer) {
                $this->markProcessed($claim, 'rejected', $admin, 'The organization no longer exists.');

                return ['success' => false, 'message' => 'The organization no longer exists.', 'status' => 422];
            }

            // Re-check eligibility under the lock — the org may have gained a real owner
            // (via another claim or a team invite) since the claim was filed.
            if (! $organizer->isClaimable()) {
                return ['success' => false, 'message' => 'This organization is no longer available to claim.', 'status' => 422];
            }

            $claimant = $claim->user;

            // Transfer ownership.
            $organizer->update(['user_id' => $claimant->id]);
            // sync() replaces the whole team: drops all prior (staff) members and makes the
            // claimant the sole owner. The unique (organizer_id, user_id) pivot key keeps this safe.
            $organizer->users()->sync([$claimant->id => ['role' => 'owner']]);

            // Attribute the organizer's events to the new owner (incl. soft-deleted, so a later
            // restore stays consistent). PublishedScope only adds an ORDER BY, so drop it on a bulk update.
            Event::withoutGlobalScope(PublishedScope::class)
                ->withTrashed()
                ->where('organizer_id', $organizer->id)
                ->update(['user_id' => $claimant->id]);

            // Land the claimant on their newly-owned org next visit.
            $claimant->update(['current_team_id' => $organizer->id]);

            $this->markProcessed($claim, 'approved', $admin);

            // Auto-reject sibling pending claims for the same organizer.
            $siblings = OwnershipClaim::where('organizer_id', $organizer->id)
                ->where('id', '!=', $claim->id)
                ->where('status', 'pending')
                ->with('user')
                ->get();

            foreach ($siblings as $sibling) {
                $this->markProcessed($sibling, 'rejected', $admin, 'Ownership was granted to another claimant.');
            }

            return [
                'success' => true,
                'message' => 'Ownership claim approved.',
                'status' => 200,
                'claim' => $claim,
                'organizer' => $organizer,
                'siblings' => $siblings,
            ];
        });

        if ($result['success']) {
            $this->notifyApproved($result['claim'], $result['organizer']);

            foreach ($result['siblings'] as $sibling) {
                $this->notifyRejected($sibling);
            }
        }

        return $result;
    }

    /**
     * Reject a claim with an admin-supplied reason (stored in admin_notes) and notify the claimant.
     */
    public function reject(OwnershipClaim $claim, User $admin, ?string $reason = null): array
    {
        // Mirror approve(): lock + re-check under the lock so a reject can't race an approve
        // and overwrite an already-processed claim with stale state.
        $result = DB::transaction(function () use ($claim, $admin, $reason) {
            $claim = OwnershipClaim::whereKey($claim->id)->lockForUpdate()->first();

            if (! $claim || $claim->status !== 'pending') {
                return ['success' => false, 'message' => 'This claim has already been processed.', 'status' => 422];
            }

            $this->markProcessed($claim, 'rejected', $admin, $reason);

            return ['success' => true, 'message' => 'Ownership claim rejected.', 'status' => 200, 'claim' => $claim];
        });

        if ($result['success']) {
            $this->notifyRejected($result['claim']);
        }

        return $result;
    }

    /**
     * Set the admin-controlled status fields by direct assignment (never mass-assigned).
     */
    private function markProcessed(OwnershipClaim $claim, string $status, User $admin, ?string $adminNotes = null): void
    {
        $claim->status = $status;
        $claim->processed_by = $admin->id;
        $claim->processed_at = now();
        if ($adminNotes !== null) {
            $claim->admin_notes = $adminNotes;
        }
        $claim->save();
    }

    private function notifyAdmins(OwnershipClaim $claim): void
    {
        $claim->loadMissing(['organizer', 'user']);

        // Ownership claims are organizer-domain — respect each admin's opt-out.
        // Per-recipient try/catch so one bad address doesn't abort the rest.
        $admins = User::where('type', 'a')->get()->filter(fn ($admin) => $admin->wantsNotification('organizers'));
        foreach ($admins as $admin) {
            try {
                Mail::to($admin)->send(new OwnershipClaimNotification($claim, true));
            } catch (\Exception $e) {
                Log::error('Failed to send ownership-claim admin notification:', ['admin_id' => $admin->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function notifyApproved(OwnershipClaim $claim, Organizer $organizer): void
    {
        try {
            $claim->loadMissing(['user', 'organizer']);
            if ($claim->user) {
                Mail::to($claim->user)->send(new OwnershipClaimNotification($claim, false));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send ownership-claim approval email:', ['error' => $e->getMessage()]);
        }

        try {
            // $organizer->user_id is the claimant after the transfer, so the in-app message
            // is delivered to them.
            Message::notification($organizer, 'Your ownership claim for '.$organizer->name.' was approved. Welcome aboard!', $organizer->slug);
        } catch (\Exception $e) {
            Log::error('Failed to send ownership-claim in-app notification:', ['error' => $e->getMessage()]);
        }
    }

    private function notifyRejected(OwnershipClaim $claim): void
    {
        try {
            $claim->loadMissing(['organizer', 'user']);
            if ($claim->user) {
                Mail::to($claim->user)->send(new OwnershipClaimNotification($claim, false));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send ownership-claim rejection email:', ['error' => $e->getMessage()]);
        }
    }
}
