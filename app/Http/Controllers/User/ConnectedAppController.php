<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Passport\Token;

/**
 * The assistants a user has connected through the OAuth consent screen —
 * the "Connected apps" list on the account settings page, and its Revoke.
 *
 * Passport's own /oauth/tokens JSON API is not used: it hides tokens whose
 * client has no owner, and dynamically registered clients (every assistant)
 * are exactly that.
 */
class ConnectedAppController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'apps' => $this->grants($request->user())
                ->with(['client', 'refreshToken'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Token $token) => [
                    'id' => $token->id,
                    'app' => $token->client?->name ?? 'Unknown app',
                    'scopes' => $token->scopes,
                    'connected_at' => $token->created_at,
                    // The access token lasts an hour and is renewed silently;
                    // the refresh token's expiry is when the connection itself
                    // lapses, which is the date worth showing.
                    'expires_at' => $token->refreshToken?->expires_at ?? $token->expires_at,
                ])->values(),
        ]);
    }

    public function destroy(Request $request, string $tokenId)
    {
        $token = $this->grants($request->user())->where('id', $tokenId)->first();

        if (! $token) {
            return response()->json(['message' => 'Connection not found.'], 404);
        }

        // The refresh token too, or the app would simply mint a new access
        // token and carry on.
        $token->revoke();
        $token->refreshToken?->revoke();

        return response()->json(['message' => 'Disconnected.']);
    }

    /**
     * Live OAuth grants: issued to an authorization-code client (the API keys
     * are listed by ApiTokenController), not revoked, and still able to act —
     * either the hour-long access token is current, or its 30-day refresh
     * token is, which is the case for every connection between uses. Keyed
     * on the access token alone, a dormant connection vanished from this
     * list while its app could still mint a fresh token.
     */
    protected function grants(User $user)
    {
        return $user->tokens()
            ->where('revoked', false)
            ->where(fn ($query) => $query
                ->where('expires_at', '>', now())
                ->orWhereHas('refreshToken', fn ($refresh) => $refresh->where('revoked', false)->where('expires_at', '>', now())))
            ->whereHas('client', fn ($query) => $query->whereJsonContains('grant_types', 'authorization_code'));
    }
}
