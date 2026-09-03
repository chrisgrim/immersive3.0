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
                ->with('client')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Token $token) => [
                    'id' => $token->id,
                    'app' => $token->client?->name ?? 'Unknown app',
                    'scopes' => $token->scopes,
                    'connected_at' => $token->created_at,
                    'expires_at' => $token->expires_at,
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
     * Live OAuth grants: not revoked, not expired, and issued to an
     * authorization-code client rather than the personal-access one (those
     * are the API keys, listed by ApiTokenController).
     */
    protected function grants(User $user)
    {
        return $user->tokens()
            ->where('revoked', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('client', fn ($query) => $query->whereJsonContains('grant_types', 'authorization_code'));
    }
}
