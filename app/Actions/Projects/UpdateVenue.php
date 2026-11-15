<?php

namespace App\Actions\Projects;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Venue;
use App\Services\ImageHandler;
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
        'name' => ['sometimes', 'required', 'string', 'max:255'],
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
        'website' => ['sometimes', 'nullable', 'url', 'regex:/^https:\/\//', 'max:255'],
        'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        'instagramHandle' => ['sometimes', 'nullable', 'string', 'max:30'],
        'facebookHandle' => ['sometimes', 'nullable', 'string', 'max:50'],
        'twitterHandle' => ['sometimes', 'nullable', 'string', 'max:15'],
        'tiktokHandle' => ['sometimes', 'nullable', 'string', 'max:30'],
    ];

    public const MESSAGES = [
        'website.regex' => 'The website must start with https://',
        'latitude.required' => 'Pick the address from the list so the map knows where it is.',
        'latitude.required_with' => 'Pick the address from the list so the map knows where it is.',
        'longitude.required_with' => 'Pick the address from the list so the map knows where it is.',
    ];

    public const IMAGE_RULES = ['image' => ['required', 'image', 'max:10240']];

    public function handle(Venue $venue, array $validated, User $actor): Venue
    {
        // A typed "@" is dropped: every page puts its own in front.
        foreach (['instagramHandle', 'twitterHandle', 'tiktokHandle'] as $handle) {
            if (array_key_exists($handle, $validated)) {
                $validated[$handle] = OrganizerRules::normalizeHandle($validated[$handle]);
            }
        }

        if (isset($validated['name']) && $validated['name'] !== $venue->name) {
            $validated['slug'] = Venue::uniqueSlug($validated['name'], $venue->id);
        }

        $venue->update($validated);

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
