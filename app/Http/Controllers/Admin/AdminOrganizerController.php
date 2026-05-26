<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\Comments;
use App\Models\Messaging\Message;

class AdminOrganizerController extends Controller
{
    public function index(Request $request)
    {
        $query = Organizer::query()
            ->with(['owner', 'users'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%");
                });
            })
            ->when($request->sort, function ($query, $sort) {
                switch ($sort) {
                    case 'oldest':
                        $query->oldest();
                        break;
                    case 'newest':
                    default:
                        $query->latest();
                        break;
                }
            }, function ($query) {
                $query->latest(); // Default sort
            });

        return $query->paginate(20);
    }

    public function update(Request $request, Organizer $organizer)
    {
        switch($request->action) {
            case 'add_member':
                $organizer->users()->syncWithoutDetaching([$request->user_id => ['role' => 'moderator']]);
                
                // Update user's current_team_id if they don't have one set
                $user = User::find($request->user_id);
                if ($user && is_null($user->current_team_id)) {
                    $user->update(['current_team_id' => $organizer->id]);
                }
                break;
            
            case 'remove_member':
                $user = User::find($request->user_id);
                
                // Check if this organizer is the user's current team
                if ($user && $user->current_team_id == $organizer->id) {
                    // Find another organization this user belongs to
                    $otherOrganizer = $user->organizers()
                        ->where('id', '!=', $organizer->id)
                        ->first();
                    
                    if ($otherOrganizer) {
                        // Set current_team_id to another organization
                        $user->update(['current_team_id' => $otherOrganizer->id]);
                    } else {
                        // No other organizations, set to NULL
                        $user->update(['current_team_id' => null]);
                    }
                }
                
                $organizer->users()->detach($request->user_id);
                break;
            
            case 'update_owner':
                $organizer->update(['user_id' => $request->user_id]);
                break;
            
            default:
                $validated = $request->validate([
                    'name' => ['sometimes', 'required', 'string', 'max:255'],
                    'email' => ['sometimes', 'required', 'email'],
                ]);
                
                $organizer->update($validated);
        }

        return $organizer->fresh(['owner', 'users']);
    }

    public function destroy(Organizer $organizer)
    {
        $organizer->deleteOrganizer($organizer);
        return response()->json(['message' => 'Organizer deleted successfully']);
    }

    /**
     * Move all events from this organizer to another. Optionally swap slugs
     * (destination takes the source's clean slug; source gets renamed with an
     * "-old" suffix). Used by admins to clean up duplicate organizer entries
     * (e.g. real venue creates their own org and we want to consolidate).
     *
     * The source organizer is NOT deleted — admin can delete manually after
     * reviewing the result.
     */
    public function moveEvents(Request $request, Organizer $organizer)
    {
        $validated = $request->validate([
            'destination_organizer_id' => 'required|integer|exists:organizers,id',
            'swap_slug' => 'sometimes|boolean',
        ]);

        if ((int) $validated['destination_organizer_id'] === $organizer->id) {
            return response()->json([
                'message' => 'Source and destination organizers must be different.',
            ], 422);
        }

        $destination = Organizer::findOrFail($validated['destination_organizer_id']);
        $sourceSlug = $organizer->slug;
        $movedCount = 0;

        DB::transaction(function () use ($organizer, $destination, $validated, $sourceSlug, &$movedCount) {
            // Move events. Includes soft-deleted in case admin wants those moved too.
            $movedCount = Event::withTrashed()
                ->where('organizer_id', $organizer->id)
                ->update(['organizer_id' => $destination->id]);

            if (! empty($validated['swap_slug'])) {
                // Source becomes "{slug}-old" (or "-old-2", "-old-3", ... if taken).
                // Then destination takes the source's original clean slug.
                $organizer->update(['slug' => $this->generateOldSlug($sourceSlug)]);
                $destination->update(['slug' => $sourceSlug]);
            }
        });

        // Notify the source organizer's owner — they need to know events moved.
        if ($organizer->user && auth()->id() !== $organizer->user->id) {
            $body = Message::MESSAGES['EVENTS_MOVED'] .
                " {$movedCount} event(s) were moved from \"{$organizer->name}\" to \"{$destination->name}\".";

            try {
                Message::notification($organizer->fresh(), $body, $destination->fresh()->slug);
                Mail::to($organizer->user)->send(new Comments($organizer->fresh(), $body, 'events_moved'));
            } catch (\Exception $e) {
                \Log::warning('Events-moved notification failed: '.$e->getMessage());
            }
        }

        return response()->json([
            'message' => "Moved {$movedCount} event(s).",
            'moved_count' => $movedCount,
            'source' => $organizer->fresh()->load(['owner', 'users']),
            'destination' => $destination->fresh()->load(['owner', 'users']),
        ]);
    }

    /**
     * Find the next available "{slug}-old" variant. Tries `-old`, then
     * `-old-2`, `-old-3`, etc. until one is unused.
     */
    protected function generateOldSlug(string $cleanSlug): string
    {
        $candidate = $cleanSlug.'-old';
        $i = 2;
        while (Organizer::where('slug', $candidate)->exists()) {
            $candidate = $cleanSlug.'-old-'.$i;
            $i++;
            if ($i > 100) {
                // Safety valve — exceptional case, fall back to timestamped.
                return $cleanSlug.'-old-'.now()->timestamp;
            }
        }

        return $candidate;
    }

    public function getPending()
    {
        return Organizer::where('status', 'r')
            ->with(['owner', 'images'])
            ->latest()
            ->paginate(20);
    }

    public function show(Organizer $organizer)
    {
        return $organizer->load([
            'owner', 
            'images',
            'users'
        ]);
    }

    public function approve(Organizer $organizer)
    {
        $organizer->update(['status' => 'p']);

        // Send notifications if not self-approving
        if (auth()->id() !== $organizer->user_id) {
            $message = Message::MESSAGES['ORGANIZER_APPROVED'];
            
            // Send in-app notification
            Message::notification($organizer, $message, $organizer->slug);
            
            // Send email notification
            Mail::to($organizer->user)->send(new Comments($organizer, $message, 'approved'));
        }

        return response()->json(['message' => 'Organizer approved successfully']);
    }

    public function reject(Request $request, Organizer $organizer)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        $organizer->update([
            'status' => 'n',
            'rejection_reason' => $validated['reason']
        ]);

        // Create rejection message with reason
        $message = "We've reviewed your organizer and have some feedback that needs to be addressed.\n\nReason: {$validated['reason']}";
        $inAppMessage = "We've reviewed your organizer and have some feedback that needs to be addressed.\n\nReason: {$validated['reason']}";
        
        if(auth()->id() !== $organizer->user_id) {
            $message = Message::MESSAGES['ORGANIZER_REJECTED'] . "\n\nReason: {$validated['reason']}";
            
            // Send in-app notification
            Message::notification($organizer, $inAppMessage, $organizer->slug);
            
            // Send email notification
            Mail::to($organizer->user)->send(new Comments($organizer, $message, 'rejected'));
        }

        return response()->json([
            'message' => 'Organizer rejected successfully',
            'organizer' => $organizer->fresh()
        ]);
    }
} 