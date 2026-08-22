<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserDeletionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->with(['teams']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($verified = $request->input('verified')) {
            if ($verified === '1') {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        return $query->paginate(20);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'type' => ['sometimes', 'required', 'string', Rule::in(['a', 'm', 'c', 'g'])],
            'verified' => 'sometimes|boolean',
        ]);

        // Privilege-escalation guard: the route is moderator-gated so moderators
        // can manage day-to-day user fields (name, email, verified), but only
        // admins may change `type`. Self-edits on `type` are refused for all
        // callers — even admins — to prevent a sole admin from demoting
        // themselves and locking the system out of admin entirely.
        if (array_key_exists('type', $validated)) {
            if (auth()->user()->type !== 'a') {
                abort(403, 'Only admins can change user roles.');
            }
            if ($user->id === auth()->id()) {
                abort(403, 'You cannot change your own role.');
            }
        }

        // Handle verification separately
        if (isset($validated['verified'])) {
            $user->email_verified_at = $validated['verified'] ? now() : null;
            unset($validated['verified']); // Remove from validated data before update
        }

        $user->update($validated);

        return response()->json($user->fresh());
    }

    public function destroy(User $user, UserDeletionService $deletions)
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 403);
        }

        if ($user->type === 'a' && auth()->user()->type !== 'a') {
            return response()->json([
                'message' => 'You do not have permission to delete admin users',
            ], 403);
        }

        // Same ownership-safety guard and cleanup as self-service deletion
        // (AccountDeletionController) — this used to only detach
        // conversation_user before a raw delete(), with no check at all for
        // Organizer/Event/Community ownership, silently orphaning any of
        // those if a moderator deleted a user who owned one.
        if ($reason = $deletions->blockingReason($user)) {
            return response()->json(['message' => $reason], 422);
        }

        try {
            $deletions->delete($user);

            return response()->json([
                'message' => 'User deleted successfully',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete user. They may have associated records that need to be handled first.',
            ], 500);
        }
    }
}
