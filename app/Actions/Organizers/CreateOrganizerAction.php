<?php

namespace App\Actions\Organizers;

use App\Models\Organizer;
use App\Models\User;
use App\Services\AdminSubmissionNotifier;
use App\Services\ImageHandler;
use App\Support\Validation\OrganizerRules;
use Illuminate\Http\UploadedFile;

/**
 * The single write path for creating an organizer, shared by the web flow
 * (OrganizerController@store) and the MCP CreateOrganizer tool.
 *
 * Sequence parity matters: create -> owner pivot -> optional image ->
 * current_team_id -> status 'r' -> admin review notification.
 */
class CreateOrganizerAction
{
    public function handle(User $user, array $validated, ?UploadedFile $image = null): Organizer
    {
        // See OrganizerRules::normalizeHandle() — strips a leading "@" a
        // caller may have typed/pasted, since every display site prepends
        // its own.
        foreach (['instagramHandle', 'twitterHandle'] as $field) {
            if (array_key_exists($field, $validated)) {
                $validated[$field] = OrganizerRules::normalizeHandle($validated[$field]);
            }
        }

        // Create the organizer with user_id
        $organizer = $user->organizers()->create($validated);

        // Also attach the creating user as owner in pivot table
        $organizer->users()->attach($user->id, ['role' => 'owner']);

        if ($image) {
            ImageHandler::saveImage($image, $organizer, 800, 800, 'organizer-images');
        }

        // Update current_team_id for the user
        $user->update(['current_team_id' => $organizer->id]);

        // Update status for the organizer
        $organizer->update(['status' => 'r']);

        // Notify admins (who haven't opted out) that a new organizer entered the review queue.
        app(AdminSubmissionNotifier::class)->organizerSubmitted($organizer);

        return $organizer;
    }
}
