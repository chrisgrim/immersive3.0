<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Community;
use Illuminate\Http\Request;

class AdminCommunityController extends Controller
{
    public function index()
    {
        return Community::paginate(20);
    }

    public function getPending()
    {
        return Community::where('status', 'pending')->paginate(20);
    }

    public function approve(Community $community)
    {
        $community->update(['status' => 'approved']);
        return response()->json(['message' => 'Community approved successfully']);
    }

    public function show(Community $community)
    {
        return $community;
    }

    public function update(Request $request, Community $community)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            // Add other validation rules
        ]);

        $community->update($validated);
        return $community;
    }

    public function destroy(Community $community)
    {
        $community->delete();
        return response()->json(['message' => 'Community deleted successfully']);
    }
} 