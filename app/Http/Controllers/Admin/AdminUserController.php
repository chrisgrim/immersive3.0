<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()
            ->with(['organizers', 'teams'])
            ->withCount(['organizers', 'teams']);

        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
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
            'verified' => 'sometimes|boolean'
        ]);

        // Handle verification separately
        if (isset($validated['verified'])) {
            $user->email_verified_at = $validated['verified'] ? now() : null;
            unset($validated['verified']); // Remove from validated data before update
        }

        $user->update($validated);
        
        return response()->json($user->fresh());
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        if ($user->type === 'a' && auth()->user()->type !== 'a') {
            return response()->json([
                'message' => 'You do not have permission to delete admin users'
            ], 403);
        }

        $user->delete();
        
        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }
} 