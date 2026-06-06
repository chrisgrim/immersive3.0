<?php

namespace App\Support;

use Illuminate\Support\Str;

class Slug
{
    /**
     * Build a URL-safe base slug that is GUARANTEED to be non-empty.
     *
     * Laravel's Str::slug() returns an empty string for input it cannot
     * transliterate — CJK scripts (你好世界, こんにちは, 안녕하세요) and
     * symbol/emoji/punctuation-only names (..., 👍, !!!). An empty slug
     * becomes an empty URL path segment, which has broken routing/redirects
     * before (an empty organizer slug produced /api/admin/organizers/ → a
     * redirect to the SPA shell → ?organizerId=undefined).
     *
     * When the slug would be empty we fall back to "{prefix}-{random}" so
     * every record always has a usable, near-unique slug. Callers that need
     * strict uniqueness should still run their own collision check on the
     * returned value (this only guarantees non-emptiness).
     */
    public static function base(?string $name, string $fallbackPrefix = 'item'): string
    {
        $slug = Str::slug((string) $name);

        if ($slug === '') {
            $slug = $fallbackPrefix.'-'.Str::lower(Str::random(8));
        }

        return $slug;
    }
}
