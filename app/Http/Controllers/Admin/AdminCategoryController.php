<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ImageHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    public function index()
    {
        return Category::with('images')->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        try {
            \Log::info('Starting category creation', [
                'has_image' => $request->hasFile('image'),
                'all_data' => $request->all(),
                'files' => $request->allFiles()
            ]);

            $validated = $request->validate([
                'name' => 'required|string|unique:categories',
                'description' => 'required|string',
                'credit' => 'nullable|string',
                'rank' => 'nullable|integer',
                'remote' => 'required|boolean',
                'type' => 'required|string|in:c,g',
                'slug' => 'nullable|string',
                'image' => 'nullable|image|max:2048'
            ]);

            if (!isset($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            $category = Category::create($validated);
            \Log::info('Category created', ['category' => $category->toArray()]);

            // Handle new image upload
            if ($request->hasFile('image')) {
                \Log::info('Image file details', [
                    'mime' => $request->file('image')->getMimeType(),
                    'original_name' => $request->file('image')->getClientOriginalName(),
                    'size' => $request->file('image')->getSize()
                ]);

                try {
                    ImageHandler::saveImage(
                        $request->file('image'),
                        $category,
                        800,
                        600,
                        'category'
                    );
                    \Log::info('Image saved successfully');
                } catch (\Exception $e) {
                    \Log::error('Image save failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }

            return $category->load('images');
        } catch (\Exception $e) {
            \Log::error('Category creation failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            throw $e;
        }
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|unique:categories,name,' . $category->id,
            'description' => 'sometimes|required|string',
            'credit' => 'nullable|string',
            'rank' => 'nullable|integer',
            'remote' => 'sometimes|required|boolean',
            'type' => 'sometimes|required|string|in:c,g',
            'slug' => 'nullable|string',
            'image' => 'nullable|image|max:2048'
        ]);

        $category->update($validated);

        // Handle current images
        if ($request->has('currentImages')) {
            $currentImages = json_decode($request->input('currentImages', '[]'), true);
            ImageHandler::updateImages($category, $currentImages);
        }

        // Handle new image upload
        if ($request->hasFile('image')) {
            // Delete old images first
            foreach ($category->images as $image) {
                ImageHandler::deleteImage($image);
            }

            ImageHandler::saveImage(
                $request->file('image'),
                $category,
                800,
                600,
                'category',
                0 // rank
            );
        }

        return $category->load('images');
    }

    public function destroy(Category $category)
    {
        foreach ($category->images as $image) {
            ImageHandler::deleteImage($image);
        }
        
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
} 