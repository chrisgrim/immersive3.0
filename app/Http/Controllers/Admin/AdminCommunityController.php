<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ModerateSubmission;
use App\Http\Controllers\Controller;
use App\Models\Curated\Community;
use App\Models\Messaging\Message;
use Illuminate\Http\Request;

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
            'community' => $community->load(['owner', 'images', 'curators']),
        ]);
    }

    public function approve(Community $community)
    {
        $community->update(['status' => 'p']);

        $message = Message::MESSAGES['COMMUNITY_APPROVED'];
        app(ModerateSubmission::class)->notifyOwner($community, $community->owner, $message, $message, 'approved');

        return response()->json(['message' => 'Community approved successfully']);
    }

    public function reject(Request $request, Community $community)
    {
        $validated = $request->validate(ModerateSubmission::REASON_RULES);

        app(ModerateSubmission::class)
            ->reject($community, $community->owner, $validated['reason'], 'community', Message::MESSAGES['COMMUNITY_REJECTED']);

        return response()->json([
            'message' => 'Community rejected successfully',
            'community' => $community->fresh(),
        ]);
    }

    public function destroy(Community $community)
    {
        $community->delete();

        return response()->json(['message' => 'Community deleted successfully']);
    }
}
