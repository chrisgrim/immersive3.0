<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Personal access token management for the MCP server.
 *
 * Tokens carry the single 'mcp' ability and a 90-day expiry. The plaintext
 * token is returned exactly once, on creation; only metadata is listed.
 */
class ApiTokenController extends Controller
{
    public function index()
    {
        return view('auth.api-tokens');
    }

    public function list(Request $request)
    {
        return response()->json([
            'tokens' => $request->user()->tokens()
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:60',
        ]);

        $exists = $request->user()->tokens()->where('name', $validated['name'])->exists();
        if ($exists) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['name' => ['You already have a token with this name.']],
            ], 422);
        }

        $token = $request->user()->createToken($validated['name'], ['mcp'], now()->addDays(90));

        return response()->json([
            'token' => $token->plainTextToken,
            'name' => $validated['name'],
            'expires_at' => $token->accessToken->expires_at,
            'message' => 'Token created. Copy it now — it will not be shown again.',
        ], 201);
    }

    public function destroy(Request $request, int $tokenId)
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Token not found.'], 404);
        }

        return response()->json(['message' => 'Token revoked.']);
    }
}
