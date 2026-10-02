<?php

namespace App\Support;

/**
 * Keeps user text in server-rendered pages from running as Vue code.
 *
 * The layout mounts Vue over the whole body (`<body id="app">`), so Vue
 * compiles every Blade page as a template, and any `{{ }}` in it is evaluated
 * as JavaScript. HTML escaping does not touch braces, so an event tagline or
 * an organizer name containing `{{ ... }}` would run as script for everyone
 * who views the page. No Blade view writes a Vue mustache of its own, so every
 * `{{` that reaches the page comes from data.
 *
 * The fix puts an invisible zero-width space between any two opening braces.
 * It must be a real character, not an entity: Vue reads the page back through
 * innerHTML, which turns entities into plain braces again.
 */
class VueSafe
{
    private const BREAK = "\u{200B}";

    /** Blade's `{{ }}` echo: HTML-escape as usual, then break up mustaches. */
    public static function e(mixed $value, bool $doubleEncode = true): string
    {
        return self::html(e($value, $doubleEncode));
    }

    /** Undo html()'s change on text coming back in (RestoreVueSafeBraces). */
    public static function restore(string $text): string
    {
        return preg_replace('/\{'.self::BREAK.'(?=\{)/u', '{', $text);
    }

    /** For already-safe HTML printed raw with `{!! !!}` (e.g. purified blurbs). */
    public static function html(?string $html): string
    {
        return preg_replace('/\{(?=\{)/u', '{'.self::BREAK, (string) $html);
    }
}
