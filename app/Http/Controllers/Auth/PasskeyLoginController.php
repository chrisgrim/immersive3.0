<?php

namespace App\Http\Controllers\Auth;

use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController as PackagePasskeyLoginController;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Passkey;
use ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * The package's passkey login, plus one thing: a passkey EI no longer knows
 * (the person removed it in Account settings, or deleted their account)
 * answers with `code: unknown_credential`, so the page can tell the
 * browser's password manager to stop offering it (WebAuthn Signal API).
 */
class PasskeyLoginController extends PackagePasskeyLoginController
{
    public function store(PasskeyVerificationRequest $request, VerifyPasskey $verify): PasskeyLoginResponse
    {
        $credentialId = Base64UrlSafe::encodeUnpadded($request->credential()->rawId);

        if (! Passkey::where('credential_id', $credentialId)->exists()) {
            // Spend the one-time challenge, as a failed verify would.
            $request->session()->forget('passkey.verification_options');

            abort(response()->json([
                'message' => 'This passkey is no longer on any account. Sign in another way.',
                'code' => 'unknown_credential',
                'credential_id' => $credentialId,
                'errors' => [
                    'credential' => ['This passkey is no longer on any account. Sign in another way.'],
                ],
            ], 422));
        }

        return parent::store($request, $verify);
    }
}
