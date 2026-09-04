<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Passport\Token;

/**
 * Personal access tokens for the MCP server — the "API keys" on the account
 * settings page, for scripts and clients that cannot do the OAuth browser
 * flow. Passport-signed JWTs with a 90-day expiry (AppServiceProvider),
 * scope mcp:use, plus mcp:moderate only when a moderator asks for it in so
 * many words. The token string is returned exactly once, on creation.
 *
 * OAuth grants (assistants connected through the consent screen) are not
 * listed here; they are the "Connected apps" list, read from Passport's
 * /oauth/tokens endpoint (routes/oauth.php).
 */
class ApiTokenController extends Controller
{
    public function index()
    {
        return view('auth.api-tokens');
    }

    public function list(Request $request)
    {
        $tokens = $this->personalTokens($request->user())
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'scopes', 'expires_at', 'created_at']);

        return response()->json([
            'tokens' => $tokens->map(fn (Token $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'scopes' => $token->scopes,
                'moderate' => in_array(User::MODERATE_SCOPE, $token->scopes ?? [], true),
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:60',
            'moderate' => 'sometimes|boolean',
        ]);

        $user = $request->user();

        if ($this->personalTokens($user)->where('name', $validated['name'])->exists()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['name' => ['You already have a token with this name.']],
            ], 422);
        }

        $scopes = ['mcp:use'];

        if ($request->boolean('moderate')) {
            // isModerator() is credential-aware; on this web session it is the
            // plain role check, which is exactly what should decide this.
            if (! $user->isModerator()) {
                return response()->json(['message' => 'Only moderators can create a token with moderator powers.'], 403);
            }

            $scopes[] = User::MODERATE_SCOPE;
        }

        $result = $user->createToken($validated['name'], $scopes);

        return response()->json([
            'token' => $result->accessToken,
            'name' => $validated['name'],
            'scopes' => $scopes,
            'expires_at' => $result->getToken()?->expires_at,
            'message' => 'Token created. Copy it now — it will not be shown again.',
        ], 201);
    }

    public function destroy(Request $request, string $tokenId)
    {
        $token = $this->personalTokens($request->user())->where('id', $tokenId)->first();

        if (! $token) {
            return response()->json(['message' => 'Token not found.'], 404);
        }

        $token->revoke();

        return response()->json(['message' => 'Token revoked.']);
    }

    /**
     * The user's live personal access tokens: not revoked, not expired, and
     * issued through the personal-access client rather than an OAuth grant.
     */
    protected function personalTokens(User $user)
    {
        return $user->tokens()
            ->where('revoked', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('client', fn ($query) => $query->whereJsonContains('grant_types', 'personal_access'));
    }
}
