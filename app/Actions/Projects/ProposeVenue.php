<?php

namespace App\Actions\Projects;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Venue;

/** Anyone on a team proposes a venue; a moderator approves it. It waits as pending. */
class ProposeVenue
{
    public const RULES = [
        'name' => ['required', 'string', 'max:80', 'regex:/[\p{L}\p{N}]/u'],
        'room' => ['nullable', 'string', 'max:255'],
        'home' => ['nullable', 'string', 'max:255'],
        'street' => ['required', 'string', 'max:255'],
        'city' => ['required', 'string', 'max:255'],
        'region' => ['nullable', 'string', 'max:255'],
        'region_long' => ['nullable', 'string', 'max:255'],
        'country' => ['nullable', 'string', 'max:255'],
        'country_long' => ['nullable', 'string', 'max:255'],
        'postal_code' => ['nullable', 'string', 'max:20'],
        'latitude' => ['required', 'numeric', 'between:-90,90'],
        'longitude' => ['required', 'numeric', 'between:-180,180'],
    ];

    public function handle(array $validated, User $actor): Venue
    {
        $venue = Venue::create([...$validated, 'status' => Venue::PENDING]);

        AuditLog::record($venue, 'venue.proposed', $actor);

        return $venue;
    }
}
