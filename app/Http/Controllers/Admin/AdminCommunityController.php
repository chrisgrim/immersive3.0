<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curated\Community;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\Comments;
use App\Models\Messaging\Message;

class AdminCommunityController extends Controller
{
    public function index(Request $request)
    {
        return Community::with(['owner', 'images'])
            ->latest()
            ->paginate(20);
    }

    public function getPending()
    {
        return Community::where('status', 'r')
            ->with(['owner', 'images', 'curators'])
            ->latest()
            ->paginate(20);
    }

    public function show(Community $community)
    {
        return response()->json([
            'community' => $community->load(['owner', 'images', 'curators'])
        ]);
    }

    public function approve(Community $community)
    {
        $community->update(['status' => 'p']);

        // Send notifications if not self-approving
        if (auth()->id() !== $community->user_id) {
            $message = Message::MESSAGES['COMMUNITY_APPROVED'];
            
            // Send in-app notification
            Message::notification($community, $message, $community->slug);
            
            // Send email notification
            Mail::to($community->user)->send(new Comments($community, $message, 'approved'));
        }

        return response()->json(['message' => 'Community approved successfully']);
    }

    public function reject(Request $request, Community $community)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        $community->update([
            'status' => 'n',
            'rejection_reason' => $validated['reason']
        ]);

        // Create rejection message with reason
        $message = "We've reviewed your community and have some feedback that needs to be addressed.\n\nReason: {$validated['reason']}";
        $inAppMessage = "We've reviewed your community and have some feedback that needs to be addressed.\n\nReason: {$validated['reason']}";
        
        if(auth()->id() !== $community->user_id) {
            $message = Message::MESSAGES['COMMUNITY_REJECTED'] . "\n\nReason: {$validated['reason']}";
            
            // Send in-app notification
            Message::notification($community, $inAppMessage, $community->slug);
            
            // Send email notification
            Mail::to($community->user)->send(new Comments($community, $message, 'rejected'));
        }

        return response()->json([
            'message' => 'Community rejected successfully',
            'community' => $community->fresh()
        ]);
    }

    public function destroy(Community $community)
    {
        $community->delete();
        return response()->json(['message' => 'Community deleted successfully']);
    }
} 