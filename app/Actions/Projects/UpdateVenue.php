<?php

namespace App\Actions\Projects;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Venue;
use App\Services\ImageHandler;
use App\Services\NameChangeRequestService;
use App\Support\Projects\VenueAmenities;
use App\Support\Validation\OrganizerRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Editing a venue's own page: the same for the team that keeps it and for
 * staff (admin screen), so the two can never drift apart.
 */
class UpdateVenue
{
    public const RULES = [
        'name' => ['sometimes', 'required', 'string', 'max:255', 'regex:/[\p{L}\p{N}]/u'],
        'room' => ['sometimes', 'nullable', 'string', 'max:255'],
        'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        'directions' => ['sometimes', 'nullable', 'string', 'max:5000'],

        // Where it is: the same parts a listing's address has, from the address search.
        'home' => ['sometimes', 'nullable', 'string', 'max:255'],
        'street' => ['sometimes', 'nullable', 'string', 'max:255'],
        'city' => ['sometimes', 'required', 'string', 'max:255'],
        'region' => ['sometimes', 'nullable', 'string', 'max:255'],
        'region_long' => ['sometimes', 'nullable', 'string', 'max:255'],
        'country' => ['sometimes', 'nullable', 'string', 'max:255'],
        'country_long' => ['sometimes', 'nullable', 'string', 'max:255'],
        'postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
        // A changed street or city must come with its map point (picked from the address search).
        'latitude' => ['required_with:street,city', 'nullable', 'numeric', 'between:-90,90'],
        'longitude' => ['required_with:street,city', 'nullable', 'numeric', 'between:-180,180'],

        // Contact, as an organizer page has it (OrganizerRules::contact), plus phone and TikTok.
        // prepare() tidies them first (https://, dashes, a pasted profile link).
        // A website needs a real host name (google.com, not google).
        'website' => ['sometimes', 'nullable', 'url', 'regex:/^https:\/\/[^\/?#\s]+\.[a-z]{2,}([\/?#:]|$)/i', 'max:255'],
        'email' => ['sometimes', 'nullable', 'email:rfc', 'regex:/@[^@\s]+\.[a-z]{2,}$/i', 'max:255'],
        // 7 to 15 digits (the world's longest), in the usual marks; one written in the
        // US way, 3-3 then one digit short or over (832-443-807, 832-443-03034), is
        // a mistyped US number; 832-443-8072 (with an optional 1- first) is right.
        'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[\d\s().-]+$/', 'regex:/^(?:\D*\d){7,15}\D*$/', 'not_regex:/^(?:1-)?\d{3}-\d{3}-(?:\d{3}|\d{5,})$/'],
        // Each site's own letters for a name: Instagram and TikTok letters, numbers, _ and .;
        // X letters, numbers and _; Facebook letters, numbers and . (a page name).
        'instagramHandle' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9._]+$/'],
        'facebookHandle' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9.\-]+$/'],
        'twitterHandle' => ['sometimes', 'nullable', 'string', 'max:15', 'regex:/^[A-Za-z0-9_]+$/'],
        'tiktokHandle' => ['sometimes', 'nullable', 'string', 'max:24', 'regex:/^[A-Za-z0-9._]+$/'],

        // What it has, from the tiles (VenueAmenities): all optional.
        'amenities' => ['sometimes', 'nullable', 'array', 'max:50'],
        // A known key, or the venue's own words as {group, label}: VenueAmenities::clean() keeps the good ones.
        'amenities.*' => ['required'],
        'amenities.*.label' => ['sometimes', 'string', 'max:200'],
    ];

    public const MESSAGES = [
        'website.url' => 'That website does not look right. Try something like venue.com.',
        'website.regex' => 'That website does not look right. Try something like https://venue.com.',
        'email.email' => 'That email does not look right. Try something like hello@venue.com.',
        'email.regex' => 'That email does not look right. Try something like hello@venue.com.',
        'phone.regex' => 'That phone number does not look right. Use numbers, with spaces, dashes or a + if you like.',
        'phone.not_regex' => 'That phone number does not look right. A US number has 10 digits, like 832-443-8072.',
        'instagramHandle.regex' => 'An Instagram name has only letters, numbers, dots and underscores.',
        'facebookHandle.regex' => 'A Facebook name has only letters, numbers, dots and dashes.',
        'twitterHandle.regex' => 'An X name has only letters, numbers and underscores.',
        'tiktokHandle.regex' => 'A TikTok name has only letters, numbers, dots and underscores.',
        'tiktokHandle.max' => 'A TikTok name is at most 24 characters.',
        'latitude.required' => 'Pick the address from the list so the map knows where it is.',
        'latitude.required_with' => 'Pick the address from the list so the map knows where it is.',
        'longitude.required_with' => 'Pick the address from the list so the map knows where it is.',
    ];

    /**
     * Before the rules: a website typed without its start ("google.com")
     * gets https:// in front, as people type addresses, and a phone of ten
     * plain digits gets its dashes (832-443-8072), as the form adds them.
     */
    public static function prepare(\Illuminate\Http\Request $request): void
    {
        $website = $request->input('website');
        if (is_string($website) && ($website = trim($website)) !== '' && ! preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $website)) {
            $request->merge(['website' => 'https://'.$website]);
        }
        $phone = $request->input('phone');
        if (is_string($phone)) {
            $phone = trim($phone);
            $request->merge(['phone' => preg_match('/^\d{10}$/', $phone) ? substr($phone, 0, 3).'-'.substr($phone, 3, 3).'-'.substr($phone, 6) : ($phone ?: null)]);
        }
        // A handle with its "@", or a pasted profile link, becomes the name alone.
        $sites = ['instagramHandle' => 'instagram\.com', 'facebookHandle' => '(?:m\.)?facebook\.com|fb\.com', 'twitterHandle' => 'twitter\.com|x\.com', 'tiktokHandle' => 'tiktok\.com'];
        foreach ($sites as $field => $host) {
            $handle = $request->input($field);
            if (! is_string($handle)) {
                continue;
            }
            $handle = trim($handle);
            // A profile.php?id= or pages/… link is not a page name: left as pasted, the rule refuses it.
            if (preg_match('#^(?:https?://)?(?:www\.)?(?:'.$host.')/@?([^/?\#\s]+)#i', $handle, $match)
                && ! in_array(strtolower($match[1]), ['profile.php', 'pages', 'people', 'groups', 'p'], true)) {
                $handle = $match[1];
            }
            $request->merge([$field => ltrim($handle, '@') ?: null]);
        }
    }

    public const IMAGE_RULES = ['image' => ['required', 'image', 'max:10240']];

    public function handle(Venue $venue, array $validated, User $actor): Venue
    {
        // A typed "@" is dropped: every page puts its own in front.
        foreach (['instagramHandle', 'twitterHandle', 'tiktokHandle'] as $handle) {
            if (array_key_exists($handle, $validated)) {
                $validated[$handle] = OrganizerRules::normalizeHandle($validated[$handle]);
            }
        }

        // Known tiles only, one age at most.
        if (array_key_exists('amenities', $validated)) {
            $validated['amenities'] = VenueAmenities::clean($validated['amenities']) ?: null;
        }

        // A live venue's new name waits for EI, as an event's or a show run's
        // does (one request, replaced on each change; going back cancels it).
        // EI's staff, and a venue not live yet, rename straight away.
        if (isset($validated['name']) && $venue->status === Venue::APPROVED && ! $actor->isModerator()) {
            $newName = trim($validated['name']);
            unset($validated['name']);
            $pending = $venue->nameChangeRequests()->where('status', 'pending')->first();

            if ($newName === $venue->name) {
                $pending?->update(['status' => 'withdrawn']);
            } elseif ($pending) {
                $pending->update(['requested_name' => $newName]);
            } else {
                app(NameChangeRequestService::class)->handleNameChange($venue, $newName);
            }
        }

        if (isset($validated['name']) && $validated['name'] !== $venue->name) {
            $validated['slug'] = Venue::uniqueSlug($validated['name'], $venue->id);
        }

        $venue->update($validated);

        if ($venue->wasChanged('name')) {
            NameChangeRequestService::settleAfterDirectRename($venue, $venue->name);
        }

        AuditLog::record($venue, 'venue.updated', $actor, ['fields' => array_keys($validated)]);

        return $venue;
    }

    /**
     * The venue's main picture (rank 0), replacing only that one. A venue keeps
     * up to four photos now; the others are left exactly as they are.
     */
    public function image(Venue $venue, UploadedFile $file, User $actor): Venue
    {
        // Locked on the venue's row like a gallery save, so the two never interleave.
        DB::transaction(function () use ($venue, $file) {
            Venue::whereKey($venue->id)->lockForUpdate()->firstOrFail();
            ImageHandler::replaceImage($venue->images()->where('rank', 0)->get(), $file, $venue, 1200, 800, 'venue-images', 0);
        });

        AuditLog::record($venue, 'venue.image_updated', $actor);

        return $venue->refresh();
    }
}
