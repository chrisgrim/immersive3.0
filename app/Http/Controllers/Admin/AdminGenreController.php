<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\ImageHandler;

class AdminGenreController extends Controller
{
    public function index(Request $request)
    {
        $query = Genre::query()
            ->withoutGlobalScope('admin')
            ->with('images');

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
            'admin' => 'boolean',
            'image' => 'nullable|image|max:2048'
        ]);

        $genre = Genre::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'rank' => $request->rank ?? 0,
            'admin' => $request->admin ?? true,
            'user_id' => auth()->user()->id,
        ]);

        if ($request->hasFile('image')) {
            ImageHandler::saveImage(
                $request->file('image'),
                $genre,
                400,  // icon size
                400,
                'genre'
            );
        }

        return response()->json($genre->fresh()->load('images'), 201);
    }

    public function update(Request $request, Genre $genre)
    {
        // If only updating rank
        if ($request->has('rank') && count($request->all()) === 1) {
            $request->validate([
                'rank' => 'required|integer|min:0'
            ]);
            
            $genre->update(['rank' => $request->rank]);
            return response()->json($genre->fresh()->load('images'));
        }

        // If only updating admin status
        if ($request->has('admin') && count($request->all()) === 1) {
            $request->validate([
                'admin' => 'required|boolean'
            ]);
            
            $genre->update(['admin' => $request->admin]);
            return response()->json($genre->fresh()->load('images'));
        }

        // If only updating name
        if ($request->has('name') && count($request->all()) === 1) {
            $request->validate([
                'name' => 'required|string|max:255|unique:genres,name,' . $genre->id
            ]);
            
            $genre->update([
                'name' => $request->name,
                'slug' => Str::slug($request->name)
            ]);
            return response()->json($genre->fresh()->load('images'));
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'required|image|max:2048'
            ]);

            // Delete old images first
            foreach ($genre->images as $image) {
                ImageHandler::deleteImage($image);
            }

            ImageHandler::saveImage(
                $request->file('image'),
                $genre,
                400,  // icon size
                400,
                'genre'
            );
            
            return response()->json($genre->fresh()->load('images'));
        }

        // Full update
        $request->validate([
            'name' => 'required|string|max:255|unique:genres,name,' . $genre->id,
            'rank' => 'required|integer|min:0',
            'admin' => 'required|boolean'
        ]);

        $genre->updateGenre($request);

        return response()->json($genre->fresh()->load('images'));
    }

    public function destroy(Genre $genre)
    {
        $genre->delete();
        return response()->json(['message' => 'Genre deleted successfully']);
    }
}
