<?php

namespace App\Services;

use App\Models\NameChangeRequest;
use App\Models\User;
use App\Mail\NameChangeNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NameChangeRequestService
{
    public function handleNameChange($model, $newName, $reason = null)
    {
        // Check if there's already a pending request
        if ($model->nameChangeRequests()->where('status', 'pending')->exists()) {
            return [
                'success' => false,
                'message' => 'You already have a pending name change request',
                'requiresRefresh' => false
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
            'status' => 'pending'
        ]);

        // Notify admins
        try {
            $admins = User::where('type', 'a')->get();
            foreach ($admins as $admin) {
                // Mail::to($admin)->send(new NameChangeNotification($request, true));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send admin notifications:', [
                'error' => $e->getMessage()
            ]);
        }

        return [
            'success' => true,
            'message' => 'Name change request submitted for review',
            'requiresRefresh' => false
        ];
    }

    public function processAdminDirectChange($model, $newName)
    {
        $oldName = $model->name;
        $oldSlug = $model->slug;
        $newSlug = Str::slug($newName);

        // Update name and slug
        $model->update([
            'name' => $newName,
            'slug' => $newSlug
        ]);

        // Handle image paths if slug changed
        if ($newSlug !== $oldSlug && $model->images()->exists()) {
            $type = $this->getModelType($model);
            ImageHandler::moveImagesForNewSlug($model, $oldSlug, $newSlug, $type);
        }

        // Send notification to owner with old and new names
        try {
            $changeData = (object)[
                'name' => $newName,
                'original_name' => $oldName,
                'user' => $model->user,
                'type' => class_basename($model)
            ];
            Mail::to($model->user)->send(new NameChangeNotification($changeData, false));
        } catch (\Exception $e) {
            \Log::error('Failed to send user notification:', [
                'error' => $e->getMessage()
            ]);
        }

        return [
            'success' => true,
            'message' => 'Name updated successfully',
            'requiresRefresh' => $newSlug !== $oldSlug
        ];
    }

    private function getModelType($model)
    {
        return strtolower(class_basename($model));
    }
}