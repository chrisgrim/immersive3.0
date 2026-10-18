<?php

namespace App\Support\Passkeys;

use App\Models\User;
use Laravel\Passkeys\Passkeys;
use ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * What the browser needs for the WebAuthn Signal API
 * (PublicKeyCredential.signalAllAcceptedCredentials and
 * signalCurrentUserDetails): the site, this person's passkey user id and
 * the passkeys EI still accepts, all base64url. The password manager then
 * hides passkeys removed here and renames entries after an email change.
 */
class PasskeySignals
{
    public static function for(User $user): array
    {
        return [
            'rpId' => Passkeys::relyingPartyId(),
            'userId' => Base64UrlSafe::encodeUnpadded($user->getPasskeyUserHandle()),
            'allAcceptedCredentialIds' => $user->passkeys()->pluck('credential_id')->all(),
            'name' => $user->getPasskeyUsername(),
            'displayName' => $user->getPasskeyDisplayName(),
        ];
    }
}
