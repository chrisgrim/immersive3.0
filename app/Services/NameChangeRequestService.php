<?php

namespace App\Services;

use App\Mail\NameChangeNotification;
use App\Models\User;
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
                Mail::to($admin)->send(new NameChangeNotification($request, true));
            }
        } catch (\Exception $e) {
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

    public function processAdminDirectChange($model, $newName)
    {
        $oldName = $model->name;
        $oldSlug = $model->slug;
        $type = $this->getModelType($model);

        // Update name and slug. Slug::base() guarantees a non-empty, URL-safe slug
        // even for CJK / emoji / symbol-only names (which Str::slug() reduces to '').
        $model->update([
            'name' => $newName,
            'slug' => Slug::base($newName, $type),
        ]);

        // Organizer/Community regenerate the slug in their own `updating` hook, so
        // read the actually-persisted slug — not the value we passed in — before
        // relocating images, or they'd be moved under a stale/empty-slug path.
        $newSlug = $model->slug;

        // Handle image paths if slug changed
        if ($newSlug !== $oldSlug && $model->images()->exists()) {
            ImageHandler::moveImagesForNewSlug($model, $oldSlug, $newSlug, $type);
        }

        // Notify the owner. Resolve via the user_id owner FK (present on both Organizer and
        // Community) rather than $model->user, since Community has no user() relation —
        // Mail::to($model->user) would be Mail::to(null) and throw (silently swallowed).
        try {
            $owner = User::find($model->user_id);
            if ($owner) {
                $changeData = (object) [
                    'name' => $newName,
                    'original_name' => $oldName,
                    'user' => $owner,
                    'type' => class_basename($model),
                ];
                Mail::to($owner)->send(new NameChangeNotification($changeData, false));
            }
        } catch (\Exception $e) {
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

    private function getModelType($model)
    {
        return strtolower(class_basename($model));
    }
}
