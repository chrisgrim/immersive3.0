<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\Request;

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
                $organizer->users()->attach($request->user_id, ['role' => 'member']);
                break;
            
            case 'remove_member':
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

} 