<?php

namespace App\Http\Controllers\AccountSettings;

use App\Http\Controllers\Controller;
use App\Services\UserDeletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountDeletionController extends Controller
{
    /**
     * Self-service account deletion. All the actual safety logic (the
     * ownership guard, FK cleanup, transaction) lives in
     * UserDeletionService, shared with AdminUserController's
     * moderator-initiated deletion — this controller only adds what's
     * specific to a SELF-deletion: logging the requester out.
     */
    public function destroy(Request $request, UserDeletionService $deletions)
    {
        $user = $request->user();

        if ($reason = $deletions->blockingReason($user)) {
            return response()->json(['message' => $reason], 422);
        }

        // Logging out BEFORE deleting is the only safe order: SessionGuard::
        // logout() cycles the remember-token via $user->save(), and
        // Eloquent's delete() sets the model's exists flag to false — a
        // save() AFTER that turns into an INSERT, silently resurrecting the
        // row that was just deleted (this actually happened, confirmed by a
        // test asserting User::find() returned null failing even though the
        // request came back 200, before this ordering was fixed). The
        // tradeoff: on the rare TOCTOU race below (an Organizer/Event/
        // Community created in the gap between the check above and the
        // transaction), the requester ends up logged out even though
        // nothing was deleted — accepted as a minor inconvenience against a
        // correctness bug that was 100% reproducible, not a rare race.
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        try {
            $deletions->delete($user);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Account deleted']);
    }
}
