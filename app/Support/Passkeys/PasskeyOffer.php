<?php

namespace App\Support\Passkeys;

use App\Models\User;

/**
 * "Sign in faster next time?": after someone signs in with an email code or
 * Google/GitHub/Apple and has no passkey yet, the next full page offers to
 * save one (resources/js/GlobalComponents/passkey-offer.vue).
 *
 * A plain session value, not a flash: the sign-in answers with a redirect
 * or JSON, and a flash could be used up by a request in between (an
 * analytics beacon) before the page that shows the offer renders.
 */
class PasskeyOffer
{
    public const SESSION_KEY = 'passkey.offer';

    public static function queue(User $user): void
    {
        if (! $user->hasPasskeysEnabled()) {
            session()->put(self::SESSION_KEY, true);
        }
    }

    /**
     * Once per sign-in: reading it clears it. Never on the "connect an app"
     * consent page (a sign-in sent there from an AI assistant): the offer
     * waits for the next ordinary page instead of covering that choice.
     */
    public static function take(): bool
    {
        if (! auth()->check() || request()->is('oauth/*')) {
            return false;
        }

        return (bool) session()->pull(self::SESSION_KEY, false);
    }
}
