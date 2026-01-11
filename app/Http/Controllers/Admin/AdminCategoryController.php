<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\ImageHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

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
                'remote' => 'nullable|boolean',
                'type' => 'required|string|in:c,g',
                'slug' => 'nullable|string',
                'image.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:8192',
                'image_index.*' => 'required_with:image.*|integer|in:0,1',
                'applicable_attendance_types' => 'nullable|array',
                'applicable_attendance_types.*' => 'integer'
            ]);

            if (!isset($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }
            
            // Ensure attendance types are not double-encoded
            if (isset($validated['applicable_attendance_types'])) {
                // Convert string values to integers
                $validated['applicable_attendance_types'] = array_map('intval', $validated['applicable_attendance_types']);
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
        // Handle attendance types update if present
        if ($request->has('applicable_attendance_types')) {
            // Check if it's a string (JSON) or already an array
            if (is_string($request->applicable_attendance_types)) {
                $validated = $request->validate([
                    'applicable_attendance_types' => 'nullable|string'
                ]);

                $attendanceTypes = json_decode($validated['applicable_attendance_types'] ?? '[]', true);
                
                // Convert string values to integers if they exist
                if (is_array($attendanceTypes)) {
                    $attendanceTypes = array_map('intval', $attendanceTypes);
                }
                
                $category->update([
                    'applicable_attendance_types' => $attendanceTypes
                ]);
            } else {
                // It's already an array
                $validated = $request->validate([
                    'applicable_attendance_types' => 'nullable|array',
                    'applicable_attendance_types.*' => 'integer'
                ]);
                
                if (isset($validated['applicable_attendance_types'])) {
                    $category->update([
                        'applicable_attendance_types' => array_map('intval', $validated['applicable_attendance_types'])
                    ]);
                }
            }
        }

        // Handle general field updates
        $validatedFields = $request->validate([
            'name' => 'sometimes|required|string|unique:categories,name,' . $category->id,
            'description' => 'sometimes|required|string',
            'credit' => 'nullable|string',
            'rank' => 'nullable|integer',
            'remote' => 'nullable|boolean',
            'type' => 'sometimes|required|string|in:c,g',
            'slug' => 'nullable|string',
        ]);
        
        // Only apply the validated fields that are actually present in the request
        $updateData = array_intersect_key($validatedFields, $request->all());
        if (!empty($updateData)) {
            $category->update($updateData);
        }

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

        Cache::forget('active-categories');

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