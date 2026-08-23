<?php

namespace App\Support\Validation;

/**
 * Shared organizer validation pieces, used by StoreOrganizerRequest (web)
 * and the MCP CreateOrganizer tool so the contract can never drift.
 */
class OrganizerRules
{
    /**
     * @return array<int, string>
     */
    public static function name(): array
    {
        return [
            'required',
            'string',
            'max:80',
            // Must contain at least one letter or number (Unicode-aware), so
            // symbol/emoji-only names like "...", "👍" or "!!!" are rejected.
            // CJK / Cyrillic / Arabic names pass (those are \p{L} letters).
            'regex:/[\p{L}\p{N}]/u',
        ];
    }

    public static function description(): string
    {
        return 'required|string|min:1|max:2000';
    }

    /**
     * Social media and contact rules — safe to validate whenever present.
     */
    public static function contact(): array
    {
        return [
            'email' => 'nullable|email',
            'website' => ['nullable', 'url', 'regex:/^https:\/\//'],
            'instagramHandle' => 'nullable|string|max:30',
            'twitterHandle' => 'nullable|string|max:15',
            'facebookHandle' => 'nullable|string|max:50',
            'patreon' => 'nullable|string|max:30',
        ];
    }

    /**
     * Every display site (organizer page, org's own settings sidebar, admin
     * review) prepends its own "@" to instagramHandle/twitterHandle — a
     * stored value that already starts with "@" (someone typed "@handle"
     * instead of "handle") renders as "@@handle" there, and silently breaks
     * the profile link URLs built from the raw value
     * (instagram.com/@handle is invalid). facebookHandle/patreon are never
     * "@"-prefixed on display, so this is deliberately Instagram/Twitter-only.
     */
    public static function normalizeHandle(?string $handle): ?string
    {
        if ($handle === null) {
            return null;
        }

        return ltrim(trim($handle), '@') ?: null;
    }

    public static function messages(): array
    {
        return [
            'name.required' => 'The name is required.',
            'name.max' => 'The name may not be greater than 80 characters.',
            'name.regex' => 'The name must contain at least one letter or number.',
            'description.required' => 'The description is required.',
            'description.min' => 'The description must be at least 1 character.',
            'description.max' => 'The description may not be greater than 2000 characters.',
            'email.email' => 'The email must be a valid email address.',
            'website.url' => 'The website must be a valid URL.',
            'website.regex' => 'The website must start with https://',
            'instagramHandle.max' => 'The Instagram handle may not be greater than 30 characters.',
            'twitterHandle.max' => 'The Twitter handle may not be greater than 15 characters.',
            'facebookHandle.max' => 'The Facebook handle may not be greater than 50 characters.',
            'patreon.max' => 'The Patreon handle may not be greater than 30 characters.',
            'image.required' => 'An image file is required.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, webp.',
            'image.max' => 'The image may not be greater than 8MB.',
        ];
    }
}
