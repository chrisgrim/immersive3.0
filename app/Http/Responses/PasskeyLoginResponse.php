<?php

namespace App\Http\Responses;

use App\Support\Passkeys\PasskeySignals;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

/**
 * Where to go after a passkey login (the "intended" page, like the email
 * code login, so the OAuth consent screen still comes back), plus what the
 * page tells the password manager before leaving: which of this person's
 * passkeys still exist and their current name and email.
 */
class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function toResponse($request)
    {
        $redirect = redirect()->intended('/');

        if (! $request->wantsJson()) {
            return $redirect;
        }

        return response()->json([
            'redirect' => $redirect->getTargetUrl(),
            'signals' => PasskeySignals::for($request->user()),
        ]);
    }
}
