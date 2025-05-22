<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\Request;
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
                $organizer->users()->attach($request->user_id, ['role' => 'moderator']);
                
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