<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\ImageHandler;
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
            $validated = $request->validate([
                'name' => 'required|string|unique:categories',
                'description' => 'required|string',
                'credit' => 'nullable|string',
                'rank' => 'nullable|integer',
                'remote' => 'required|boolean',
                'type' => 'required|string|in:c,g',
                'slug' => 'nullable|string',
                'image.*' => 'nullable|image|max:2048',
                'image_index.*' => 'required_with:image.*|integer|in:0,1'
            ]);

            if (!isset($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            $category = Category::create($validated);

            // Handle image uploads
            if ($request->hasFile('image')) {
                $images = $request->file('image');
                $indices = $request->input('image_index', []);
                
                foreach ($images as $key => $image) {
                    $imageIndex = $indices[$key] ?? 0;
                    $dimensions = $imageIndex === 1 ? 400 : 800;
                    
                    ImageHandler::saveImage(
                        $image,
                        $category,
                        $dimensions,
                        $dimensions,
                        'category-images',
                        $imageIndex
                    );
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
            $imageIndex = $request->input('image_index', 0); // 0 for main, 1 for icon
            
            // Find existing image with this rank
            $existingImage = $category->images()->where('rank', $imageIndex)->first();
            if ($existingImage) {
                ImageHandler::deleteImage($existingImage);
            }

            // Set dimensions based on image type
            $dimensions = $imageIndex === 1 
                ? 400  // icon size
                : 800; // main image size

            // Save new image
            ImageHandler::saveImage(
                $request->file('image'),
                $category,
                $dimensions,
                $dimensions,
                'category-images',
                $imageIndex
            );
        }

        return $category->load('images');
    }

    public function destroy(Category $category)
    {
        // Check if category has any associated events
        if ($category->events()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category because it has associated events. Please remove all events from this category first.',
                'error' => 'CATEGORY_HAS_EVENTS'
            ], 422);
        }

        // If no events, proceed with deletion
        foreach ($category->images as $image) {
            ImageHandler::deleteImage($image);
        }
        
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
} 