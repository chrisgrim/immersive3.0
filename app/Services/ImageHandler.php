<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Illuminate\Support\Facades\Log;



class ImageHandler
{
    public static function saveImage($image, $model, $width, $height, $type, $rank = 0)
    {
        // Always ensure rank is an integer
        $rank = (int)$rank;
        
        // Validate image
        $mimeType = $image->getMimeType();
        if (strpos($mimeType, 'image/') !== 0) {
            throw new \Exception('The file is not an image.');
        }

        // Get model ID and slug with fallbacks
        $modelId = uniqid();
        $modelSlug = $model->slug ?? Str::random(8);
        
        // Create directory using slug (or random string if no slug)
        $directory = "$type/$modelSlug";
        
        // Create filename using both ID and slug
        $fileName = "{$modelSlug}-{$modelId}";

        $imagePath = $image->getPathName();
        $image = Image::read($imagePath);

        // Process and save image in both jpg and webp formats
        $jpg = clone $image;
        $jpg->cover($width, $height);
        $encodedJpg = $jpg->encode(new JpegEncoder(quality: 75));
        Storage::disk('digitalocean')->put("/public/$directory/$fileName.jpg", (string) $encodedJpg);

        $webp = clone $image;
        $webp->cover($width, $height);
        $encodedWebp = $webp->encode(new WebpEncoder(quality: 75));
        Storage::disk('digitalocean')->put("/public/$directory/$fileName.webp", (string) $encodedWebp);

        $thumbJpg = clone $image;
        $thumbJpg->cover($width / 2, $height / 2);
        $encodedThumbJpg = $thumbJpg->encode(new JpegEncoder(quality: 75));
        Storage::disk('digitalocean')->put("/public/$directory/$fileName-thumb.jpg", (string) $encodedThumbJpg);

        $thumbWebp = clone $image;
        $thumbWebp->cover($width / 2, $height / 2);
        $encodedThumbWebp = $thumbWebp->encode(new WebpEncoder(quality: 75));
        Storage::disk('digitalocean')->put("/public/$directory/$fileName-thumb.webp", (string) $encodedThumbWebp);

        // Create the image with specified rank
        $createdImage = $model->images()->create([
            'large_image_path' => "$directory/$fileName.webp",
            'thumb_image_path' => "$directory/{$fileName}-thumb.webp",
            'rank' => $rank
        ]);
        
        // Only update the main event image fields if this is the primary image (rank 0)
        $table = $model->getTable();
        $hasImageColumns = \Schema::hasColumns($table, ['largeImagePath', 'thumbImagePath']);
        
        if ($hasImageColumns && $rank === 0) {
            $model->largeImagePath = "$directory/$fileName.webp";
            $model->thumbImagePath = "$directory/{$fileName}-thumb.webp";
            $model->save();
        }
        
        return $createdImage;
    }

    public static function deleteImage($image)
    {
        // Validate image path has proper subdirectory structure (type-images/slug/filename)
        $pathParts = explode('/', $image->large_image_path);
        if (count($pathParts) < 3 || !str_ends_with($pathParts[0], '-images')) {
            throw new \Exception('Invalid image path structure');
        }

        // Get the base path without extension
        $basePath = preg_replace('/\.(webp|jpg)$/', '', $image->large_image_path);
        $baseThumbPath = preg_replace('/\.(webp|jpg)$/', '', $image->thumb_image_path);
        
        // Ensure we're not in a root directory
        $directory = dirname("/public/{$image->large_image_path}");
        if ($directory === '/public' || preg_match('|^/public/[^/]+-images$|', $directory)) {
            throw new \Exception('Cannot delete from root directory');
        }

        // Delete all image formats
        Storage::disk('digitalocean')->delete([
            "/public/{$basePath}.webp",
            "/public/{$basePath}.jpg",
            "/public/{$baseThumbPath}.webp",
            "/public/{$baseThumbPath}.jpg"
        ]);

        // Get directory path
        $directory = dirname("/public/{$image->large_image_path}");
        
        // Get the parent model before deleting the image
        $parent = $image->imageable;
        
        // Store the rank before deleting
        $imageRank = $image->rank;
        
        // Delete the image record from database
        $image->delete();

        // If parent model has image path columns, only clear them if this was the main image (rank 0)
        if ($parent && \Schema::hasColumns($parent->getTable(), ['largeImagePath', 'thumbImagePath']) && $imageRank === 0) {
            $parent->update([
                'largeImagePath' => null,
                'thumbImagePath' => null
            ]);
        }

        // Check if directory is empty and delete it if it is
        try {
            $files = Storage::disk('digitalocean')->files($directory);
            if (empty($files)) {
                Storage::disk('digitalocean')->deleteDirectory($directory);
            }
        } catch (\Exception $e) {
            // Silently fail on directory cleanup errors
        }
    }

    public static function updateImages($model, $currentImages)
    {
        if ($currentImages) {
            $existingImages = $model->images->pluck('large_image_path', 'id')->toArray();
            $currentImagePaths = array_column($currentImages, 'url');
            
            $imagesToDelete = array_diff($existingImages, $currentImagePaths);

            foreach ($imagesToDelete as $id => $imagePath) {
                $image = $model->images()->find($id);
                if ($image) {
                    self::deleteImage($image);
                }
            }

            foreach ($currentImages as $index => $currentImage) {
                $image = $model->images()->where('large_image_path', $currentImage['url'])->first();
                if ($image) {
                    $image->rank = $currentImage['rank'];
                    $image->save();
                }
            }
        }
    }

    public static function finalize($model, $slug, $type)
    {
        $images = $model->images;
        $originalDirectories = [];
        
        foreach ($images as $image) {
            $newDirectory = "$type-images/$slug-final";
            $currentPath = $image->large_image_path;
            $currentDirectory = dirname($currentPath);
            $originalDirectories[] = $currentDirectory;
            
            // Extract the original filename part
            $originalFileName = basename($currentPath, '.webp');
            $modelId = uniqid();
            $newFileName = "$slug-$modelId";
            
            // Ensure the directory exists
            if (!Storage::disk('digitalocean')->exists("/public/$newDirectory")) {
                Storage::disk('digitalocean')->makeDirectory("/public/$newDirectory");
            }
            
            // Copy all image files with proper paths
            try {
                // Copy large webp
                if (Storage::disk('digitalocean')->exists("/public/$currentPath")) {
                    Storage::disk('digitalocean')->copy(
                        "/public/$currentPath", 
                        "/public/$newDirectory/$newFileName.webp"
                    );
                }
                
                // Copy large jpg
                $currentJpgPath = preg_replace('/\.webp$/', '.jpg', $currentPath);
                if (Storage::disk('digitalocean')->exists("/public/$currentJpgPath")) {
                    Storage::disk('digitalocean')->copy(
                        "/public/$currentJpgPath",
                        "/public/$newDirectory/$newFileName.jpg"
                    );
                }
                
                // Copy thumb webp
                if (Storage::disk('digitalocean')->exists("/public/$image->thumb_image_path")) {
                    Storage::disk('digitalocean')->copy(
                        "/public/$image->thumb_image_path",
                        "/public/$newDirectory/$newFileName-thumb.webp"
                    );
                }
                
                // Copy thumb jpg
                $thumbJpgPath = preg_replace('/\.webp$/', '.jpg', $image->thumb_image_path);
                if (Storage::disk('digitalocean')->exists("/public/$thumbJpgPath")) {
                    Storage::disk('digitalocean')->copy(
                        "/public/$thumbJpgPath",
                        "/public/$newDirectory/$newFileName-thumb.jpg"
                    );
                }
                
                // Update the image record with new paths
                $image->update([
                    'large_image_path' => "$newDirectory/$newFileName.webp",
                    'thumb_image_path' => "$newDirectory/$newFileName-thumb.webp",
                ]);

                // Update model image columns if they exist
                $table = $model->getTable();
                $hasImageColumns = \Schema::hasColumns($table, ['largeImagePath', 'thumbImagePath']);
                if ($hasImageColumns && $image->rank === 0) {
                    $model->largeImagePath = "$newDirectory/$newFileName.webp";
                    $model->thumbImagePath = "$newDirectory/$newFileName-thumb.webp";
                    $model->save();
                }
            } catch (\Exception $e) {
                \Log::error("Failed to copy image: " . $e->getMessage());
            }
        }
        
        // Clean up original directories after all files are copied
        $uniqueDirectories = array_unique($originalDirectories);
        foreach ($uniqueDirectories as $directory) {
            try {
                Storage::disk('digitalocean')->deleteDirectory("/public/$directory");
            } catch (\Exception $e) {
                \Log::error("Failed to delete directory: " . $e->getMessage());
            }
        }
    }

    public static function moveImagesForNewSlug($model, $oldSlug, $newSlug, $type)
    {

        if ($model->images()->exists()) {
            foreach ($model->images as $index => $image) {

                $currentDirectory = dirname($image->large_image_path);
                $newDirectory = str_replace($oldSlug, $newSlug, $currentDirectory);
                
                $modelId = uniqid();
                $newFileName = "$newSlug-$modelId";
                
                try {
                    // Check if source files exist
                    $largeWebpExists = Storage::disk('digitalocean')->exists("/public/$image->large_image_path");
                    $largeJpgPath = preg_replace('/\.webp$/', '.jpg', $image->large_image_path);
                    $largeJpgExists = Storage::disk('digitalocean')->exists("/public/$largeJpgPath");
                    $thumbWebpExists = Storage::disk('digitalocean')->exists("/public/$image->thumb_image_path");
                    $thumbJpgPath = preg_replace('/\.webp$/', '.jpg', $image->thumb_image_path);
                    $thumbJpgExists = Storage::disk('digitalocean')->exists("/public/$thumbJpgPath");

                    // Log a warning if any source files are missing
                    if (!$largeWebpExists || !$largeJpgExists || !$thumbWebpExists || !$thumbJpgExists) {
                        Log::warning("Some source files missing for image {$image->id}", [
                            'largeWebp' => $largeWebpExists,
                            'largeJpg' => $largeJpgExists, 
                            'thumbWebp' => $thumbWebpExists,
                            'thumbJpg' => $thumbJpgExists
                        ]);
                    }

                    // Ensure destination directory exists
                    if (!Storage::disk('digitalocean')->exists("/public/$newDirectory")) {
                        Storage::disk('digitalocean')->makeDirectory("/public/$newDirectory");
                    }
                    
                    // Copy files
                    if ($largeWebpExists) {
                        Storage::disk('digitalocean')->copy(
                            "/public/$image->large_image_path",
                            "/public/$newDirectory/$newFileName.webp"
                        );
                    }
                    
                    if ($largeJpgExists) {
                        Storage::disk('digitalocean')->copy(
                            "/public/$largeJpgPath",
                            "/public/$newDirectory/$newFileName.jpg"
                        );
                    }
                    
                    if ($thumbWebpExists) {
                        Storage::disk('digitalocean')->copy(
                            "/public/$image->thumb_image_path",
                            "/public/$newDirectory/$newFileName-thumb.webp"
                        );
                    }
                    
                    if ($thumbJpgExists) {
                        Storage::disk('digitalocean')->copy(
                            "/public/$thumbJpgPath",
                            "/public/$newDirectory/$newFileName-thumb.jpg"
                        );
                    }

                    // Update image record
                    $oldLargePath = $image->large_image_path;
                    
                    $image->update([
                        'large_image_path' => "$newDirectory/$newFileName.webp",
                        'thumb_image_path' => "$newDirectory/$newFileName-thumb.webp",
                    ]);
                    
                    // Update model image columns if needed
                    $table = $model->getTable();
                    $hasImageColumns = \Schema::hasColumns($table, ['largeImagePath', 'thumbImagePath']);
                    if ($hasImageColumns && $image->rank === 0) {
                        $model->largeImagePath = "$newDirectory/$newFileName.webp";
                        $model->thumbImagePath = "$newDirectory/$newFileName-thumb.webp";
                        $model->save();
                    }
                } catch (\Exception $e) {
                    Log::error("Error processing image {$image->id}: {$e->getMessage()}");
                }
            }
            
            // Only delete original directories after all images are processed
            $processedDirectories = [];
            
            foreach ($model->images as $image) {
                $originalDirectory = str_replace($newSlug, $oldSlug, dirname($image->large_image_path));
                if (!in_array($originalDirectory, $processedDirectories)) {
                    $processedDirectories[] = $originalDirectory;
                    
                    try {
                        if (Storage::disk('digitalocean')->exists("/public/$originalDirectory")) {
                            Storage::disk('digitalocean')->deleteDirectory("/public/$originalDirectory");
                        }
                    } catch (\Exception $e) {
                        Log::error("Error deleting directory /public/$originalDirectory: {$e->getMessage()}");
                    }
                }
            }
        }
    }

}