<?php

namespace App\Services;

use App\Mail\NameChangeNotification;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use App\Models\Venue;
use App\Support\Slug;
use Illuminate\Support\Facades\Mail;

class NameChangeRequestService
{
    public function handleNameChange($model, $newName, $reason = null)
    {
        // Check if there's already a pending request
        if ($model->nameChangeRequests()->where('status', 'pending')->exists()) {
            return [
                'success' => false,
                'message' => 'You already have a pending name change request',
                'requiresRefresh' => false,
            ];
        }

        // Otherwise create a request
        return $this->createNameChangeRequest($model, $newName, $reason);
    }

    private function createNameChangeRequest($model, $newName, $reason)
    {
        // Create the request
        $request = $model->nameChangeRequests()->create([
            'current_name' => $model->name,
            'requested_name' => $newName,
            'user_id' => auth()->id(),
            'reason' => $reason,
            'status' => 'pending',
        ]);

        // Notify admins (name changes are organizer-domain — respect each admin's opt-out).
        try {
            $admins = User::where('type', 'a')->get()->filter(fn ($admin) => $admin->wantsNotification('organizers'));
            foreach ($admins as $admin) {
                Mail::to($admin)->queue(new NameChangeNotification($request, true));
            }
        } catch (\Exception $e) {
            report($e);
            \Log::error('Failed to send admin notifications:', [
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'success' => true,
            'message' => 'Name change request submitted for review',
            'requiresRefresh' => false,
        ];
    }

    public function processAdminDirectChange($model, $newName, ?User $requester = null)
    {
        // Checked again here, not only when the request was filed: another
        // listing may have taken the name since.
        if ($model instanceof Event && Event::nameTakenOnSite($newName, $model->id, $model->sameShowIds())) {
            return [
                'success' => false,
                'message' => "Another listing on EI is already called \"{$newName}\".",
                'requiresRefresh' => false,
            ];
        }

        $oldName = $model->name;
        $oldSlug = $model->slug;
        $type = $this->getModelType($model);

        // Update name and slug. Slug::base() guarantees a non-empty, URL-safe slug
        // even for CJK / emoji / symbol-only names (which Str::slug() reduces to '').
        // An event's slug must also be unique across every event, deleted
        // ones included (older deletions may still hold theirs), so it gets
        // the same collision-safe slug approval gives it.
        $model->name = $newName;
        // A show run keeps its address: its runs' pages all live under it.
        $model->update(match (true) {
            $model instanceof Project => ['name' => $newName],
            // A venue's old address redirects (its own updated hook).
            $model instanceof Venue => ['name' => $newName, 'slug' => Venue::uniqueSlug($newName, $model->id)],
            default => ['name' => $newName, 'slug' => $model instanceof Event ? Event::finalSlug($model) : Slug::base($newName, $type)],
        });

        // Organizer/Community regenerate the slug in their own `updating` hook, so
        // read the actually-persisted slug — not the value we passed in — before
        // relocating images, or they'd be moved under a stale/empty-slug path.
        $newSlug = $model->slug;

        // The old address redirects from now on. An organizer's rename does
        // this in its own hook, whichever path renames it.
        if ($model instanceof Event) {
            $model->rememberOldAddress($oldSlug);
        }

        // Handle image paths if slug changed
        // A venue's picture folders keep their name: rows store the paths.
        if ($newSlug !== $oldSlug && ! $model instanceof Venue && $model->images()->exists()) {
            ImageHandler::moveImagesForNewSlug($model, $oldSlug, $newSlug, $type);
        }

        // Notify the owner. Resolve via the user_id owner FK (present on both Organizer and
        // Community) rather than $model->user, since Community has no user() relation —
        // Mail::to($model->user) would be Mail::to(null) and throw (silently swallowed).
        try {
            // A show run has no owner column: the person who asked hears back.
            $owner = ($model instanceof Project || $model instanceof Venue) ? $requester : User::find($model->user_id);
            if ($owner) {
                $changeData = (object) [
                    'name' => $newName,
                    'original_name' => $oldName,
                    'user' => $owner,
                    'type' => class_basename($model),
                ];
                Mail::to($owner)->queue(new NameChangeNotification($changeData, false));
            }
        } catch (\Exception $e) {
            report($e);
            \Log::error('Failed to send user notification:', [
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'success' => true,
            'message' => 'Name updated successfully',
            'requiresRefresh' => $newSlug !== $oldSlug,
        ];
    }

    /**
     * Staff renamed it directly (an admin screen, Claude's tools, the page
     * itself): a request waiting for EI is settled, approved if it asked for
     * that very name, otherwise withdrawn, so it never overwrites the new name.
     */
    public static function settleAfterDirectRename($model, string $newName): void
    {
        $model->nameChangeRequests()->where('status', 'pending')->get()->each(fn ($pending) => $pending->update([
            'status' => $pending->requested_name === $newName ? 'approved' : 'withdrawn',
        ]));
    }

    private function getModelType($model)
    {
        return strtolower(class_basename($model));
    }
}
