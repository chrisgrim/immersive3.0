<?php

namespace App\Support;

/**
 * Where the browser lands after the consent screen, in words an organizer
 * can read.
 *
 * The redirect URI is the one thing on that screen a client cannot dress up,
 * so the screen shows it. But "localhost" reads as a glitch to anyone who is
 * not a developer, when it only means the assistant runs on their own machine
 * (Claude Code's local callback server, or a desktop editor's private
 * cursor:// scheme). Those cases say "on this computer"; a real domain
 * (claude.ai, chatgpt.com) is named, because that is the spoofing signal.
 */
final class RedirectDestination
{
    private const LOOPBACK = ['localhost', '::1', '[::1]'];

    /**
     * The public host the browser is sent to, or null when it stays on the
     * user's own computer.
     */
    public static function host(string $redirectUri): ?string
    {
        $scheme = strtolower((string) parse_url($redirectUri, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($redirectUri, PHP_URL_HOST));

        // Only http(s) carries a host worth naming: cursor://x/callback has
        // an "authority" that is an app identifier, not a place.
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        // RFC 8252 loopback: the whole 127/8 block, ::1, and localhost.
        if (in_array($host, self::LOOPBACK, true) || preg_match('/^127(\.\d{1,3}){3}$/', $host) || str_ends_with($host, '.localhost')) {
            return null;
        }

        return $host;
    }
}
