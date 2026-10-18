<?php

namespace App\Support\Passkeys;

/**
 * The logo of the password manager a passkey lives in (iCloud Keychain,
 * Google Password Manager, 1Password, ...), looked up by the AAGUID the
 * passkey reported when it was made.
 *
 * resources/data/passkey-provider-icons.json is a trimmed copy of
 * combined_aaguid.json from github.com/passkeydeveloper/passkey-authenticator-aaguids
 * (kept here because that list says it may go away): SVG logos of 24 KB or
 * less only, `dark` only where it differs from `light`. Logos are SVG data
 * URIs meant for an <img>, where an SVG cannot run scripts.
 */
class PasskeyProviderIcons
{
    private static ?array $icons = null;

    /** @return array{light: string, dark: string}|null */
    public static function for(?string $aaguid): ?array
    {
        if ($aaguid === null) {
            return null;
        }

        self::$icons ??= json_decode((string) file_get_contents(resource_path('data/passkey-provider-icons.json')), true) ?: [];

        $icon = self::$icons[strtolower($aaguid)] ?? null;

        if ($icon === null) {
            return null;
        }

        return [
            'light' => $icon['light'],
            'dark' => $icon['dark'] ?? $icon['light'],
        ];
    }
}
