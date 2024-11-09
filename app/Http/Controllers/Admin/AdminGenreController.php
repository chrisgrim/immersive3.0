<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminGenreController extends Controller
{
    public function index(Request $request)
    {
        $query = Genre::query()->withoutGlobalScope('admin');

        // Apply search filter
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Apply type filter
        if ($request->filled('type')) {
            $query->where('admin', $request->type);
        }

        // Apply sorting
        $sortField = $request->input('sort_field', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        
        // Handle special sort cases with name as secondary sort
        if (in_array($sortField, ['created_at', 'rank'])) {
            $query->orderBy($sortField, $sortDirection)
                  ->orderBy('name', 'asc');
        } else {
            $query->orderBy($sortField, $sortDirection)
                  ->orderBy('name', 'asc');
        }

        return $query->paginate(40);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:genres,name',
            'rank' => 'integer|min:0',
            'admin' => 'boolean'
        ]);

        Genre::saveGenre($request);

        return response()->json(['message' => 'Genre created successfully'], 201);
    }

    public function update(Request $request, Genre $genre)
    {
        // If only updating admin status
        if ($request->has('admin') && count($request->all()) === 1) {
            $request->validate([
                'admin' => 'required|boolean'
            ]);
            
            $genre->update(['admin' => $request->admin]);
            return response()->json($genre->fresh());
        }

        // If only updating name
        if ($request->has('name') && count($request->all()) === 1) {
            $request->validate([
                'name' => 'required|string|max:255|unique:genres,name,' . $genre->id,
            ]);
            
            $genre->update([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'admin' => $genre->admin, // Keep existing admin value
                'rank' => $genre->rank,   // Keep existing rank value
                'user_id' => auth()->user()->id
            ]);
            return response()->json($genre->fresh());
        }

        // Original validation for other cases
        $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:genres,name,' . $genre->id,
            'rank' => 'sometimes|required|integer|min:0',
            'admin' => 'sometimes|required|boolean'
        ]);

        $genre->updateGenre($request);

        return response()->json($genre->fresh());
    }

    public function destroy(Genre $genre)
    {
        $genre->delete();
        return response()->json(['message' => 'Genre deleted successfully']);
    }
}
