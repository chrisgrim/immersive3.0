<?php

namespace App\Services;

use App\Models\Curated\Community;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single safe path for deleting a User — used by BOTH self-service
 * deletion (AccountDeletionController) and moderator-initiated deletion
 * (AdminUserController). Chris's explicit instruction: never let account
 * deletion touch (let alone delete) an Organizer, Event, or Community a user
 * created, since other people (attendees, team members, curators) depend on
 * that content staying intact. organizers.user_id, events.user_id, and
 * communities.user_id have NO database foreign key at all (confirmed via a
 * full migration audit), so nothing at the DB level stops a naive delete
 * from silently orphaning them — this class is the only safety net, so it
 * must be the ONLY code path either controller uses to delete a user.
 */
class UserDeletionService
{
    /**
     * Null if the user can be deleted; otherwise the reason they can't.
     */
    public function blockingReason(User $user): ?string
    {
        // Phrased to read reasonably both to the account owner themselves
        // (self-service deletion) and to a moderator deleting someone else's
        // account, since both AccountDeletionController and AdminUserController
        // surface this same message as-is.
        if ($user->organizers()->exists()) {
            return "This account still owns an organizer page. Transfer ownership to a teammate before deleting the account, or contact us at support@everythingimmersive.com and we'll help — we don't want to take that away from the events or team members who depend on it.";
        }

        if ($user->events()->exists()) {
            return "This account still has events created under it. Transfer them before deleting the account, or contact us at support@everythingimmersive.com and we'll help.";
        }

        if (Community::where('user_id', $user->id)->exists()) {
            return "This account still owns a community. Transfer ownership before deleting the account, or contact us at support@everythingimmersive.com and we'll help.";
        }

        return null;
    }

    /**
     * Deletes the user. Callers MUST check blockingReason() first — this
     * re-checks ownership again immediately before the actual delete
     * (inside the same transaction) to narrow, though not fully eliminate,
     * the window where an Organizer/Event/Community could be created or
     * reassigned between the caller's check and the delete itself. Closing
     * that window completely would need an application-level lock on
     * ownership writes for this user, which is beyond tonight's scope —
     * unconstrained user_id columns mean there's no row to lock against at
     * the database level.
     *
     * @throws \RuntimeException if the re-check finds the user now owns
     *                           an Organizer/Event/Community that didn't exist at the caller's
     *                           earlier check.
     */
    public function delete(User $user): void
    {
        $imagesToDeleteFromStorage = [];

        DB::transaction(function () use ($user, &$imagesToDeleteFromStorage) {
            if ($reason = $this->blockingReason($user)) {
                throw new \RuntimeException($reason);
            }

            // flags/ratings have a real FK to users.id with no cascade/null
            // action (RESTRICT) — deleting the user first would throw.
            DB::table('flags')->where('user_id', $user->id)->delete();
            DB::table('ratings')->where('user_id', $user->id)->delete();

            // Pivot memberships only — never deletes the Organizer/Community
            // itself, just this user's link to it.
            $user->conversations()->detach();
            $user->teams()->detach();
            $user->communities()->detach();

            // Personal content — safe to remove, nobody else depends on it
            // the way they depend on an Event or Organizer staying intact.
            $user->favorites()->delete();
            $user->staffpicks()->delete();

            // Deleting the image ROWS is transactional; the actual file
            // deletes happen after commit (see below) — an irreversible
            // storage delete inside a transaction that then rolls back
            // would leave an existing account with broken images.
            $imagesToDeleteFromStorage = $user->images->all();
            $user->images()->delete();

            // user_locations and organizer_followers cascade-delete via a real
            // FK, so no manual cleanup needed for either.
            $user->delete();
        });

        foreach ($imagesToDeleteFromStorage as $image) {
            try {
                ImageHandler::deleteImage($image);
            } catch (\Exception $e) {
                // The DB rows are already gone (transaction committed) — a
                // failed file cleanup here is a harmless orphaned file, not
                // a data-integrity problem.
            }
        }
    }
}
