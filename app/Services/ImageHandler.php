<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Encoders\JpegEncoder;



class ImageHandler
{
    public static function saveImage($image, $model, $width, $height, $type, $rank = 0)
    {
        $mimeType = $image->getMimeType();
        if (strpos($mimeType, 'image/') !== 0) {
            throw new \Exception('The file is not an image.');
        }

        $slug = $model->slug ?? Str::slug($model->name);
        $directory = "$type-images/$slug";

        $imagePath = $image->getPathName();
        $image = Image::read($imagePath);

        $fileName = time() . '-' . Str::random(6);

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

        $model->images()->create([
            'large_image_path' => "$directory/$fileName.webp",
            'thumb_image_path' => "$directory/{$fileName}-thumb.webp",
            'rank' => $rank
        ]);

        $table = $model->getTable();
        $hasImageColumns = \Schema::hasColumns($table, ['largeImagePath', 'thumbImagePath']);

        if ($hasImageColumns) {
            $model->largeImagePath = "$directory/$fileName.webp";
            $model->thumbImagePath = "$directory/{$fileName}-thumb.webp";
            $model->save();
        }
    }

    public static function deleteImage($image)
    {
        // Validate image path has proper subdirectory structure (type-images/slug/filename)
        $pathParts = explode('/', $image->large_image_path);
        if (count($pathParts) < 3 || !str_ends_with($pathParts[0], '-images')) {
            \Log::error('Invalid image path structure detected', [
                'path' => $image->large_image_path,
                'parts' => $pathParts
            ]);
            throw new \Exception('Invalid image path structure');
        }

        // Get the base path without extension
        $basePath = preg_replace('/\.(webp|jpg)$/', '', $image->large_image_path);
        $baseThumbPath = preg_replace('/\.(webp|jpg)$/', '', $image->thumb_image_path);
        
        // Ensure we're not in a root directory
        $directory = dirname("/public/{$image->large_image_path}");
        if ($directory === '/public' || preg_match('|^/public/[^/]+-images$|', $directory)) {
            \Log::error('Attempted to delete from root directory', [
                'directory' => $directory,
                'image_path' => $image->large_image_path
            ]);
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
        
        // Delete the image record from database
        $image->delete();

        // Check if directory is empty and delete it if it is
        try {
            $files = Storage::disk('digitalocean')->files($directory);
            if (empty($files)) {
                \Log::info('Deleting empty directory', ['directory' => $directory]);
                Storage::disk('digitalocean')->deleteDirectory($directory);
            }
        } catch (\Exception $e) {
            \Log::error('Error cleaning up directory', [
                'error' => $e->getMessage(),
                'directory' => $directory
            ]);
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
                    $image->rank = $index;
                    $image->save();
                }
            }
        }
    }

    public static function finalize($model, $slug, $type)
    {
        // Get all images associated with the model
        $images = $model->images;
        
        foreach ($images as $image) {
            // Generate new random string for the final directory
            $rand = Str::random(7);
            $newDirectory = "$type-images/$slug-$rand-final";
            
            // Get current directory and filename
            $currentPath = $image->large_image_path;
            $currentDirectory = dirname($currentPath);
            $fileName = basename($currentPath, '.webp');
            
            // Copy all image variations to new location
            Storage::disk('digitalocean')->copy(
                "/public/$currentPath", 
                "/public/$newDirectory/$slug.webp"
            );
            Storage::disk('digitalocean')->copy(
                "/public/" . preg_replace('/\.webp$/', '.jpg', $currentPath),
                "/public/$newDirectory/$slug.jpg"
            );
            Storage::disk('digitalocean')->copy(
                "/public/$image->thumb_image_path",
                "/public/$newDirectory/$slug-thumb.webp"
            );
            Storage::disk('digitalocean')->copy(
                "/public/" . preg_replace('/\.webp$/', '.jpg', $image->thumb_image_path),
                "/public/$newDirectory/$slug-thumb.jpg"
            );

            // Update image paths in database
            $image->update([
                'large_image_path' => "$newDirectory/$slug.webp",
                'thumb_image_path' => "$newDirectory/$slug-thumb.webp",
            ]);

            // Update model's image paths if columns exist
            $table = $model->getTable();
            $hasImageColumns = \Schema::hasColumns($table, ['largeImagePath', 'thumbImagePath']);
            if ($hasImageColumns) {
                $model->largeImagePath = "$newDirectory/$slug.webp";
                $model->thumbImagePath = "$newDirectory/$slug-thumb.webp";
                $model->save();
            }

            // Delete the original directory
            Storage::disk('digitalocean')->deleteDirectory("/public/$currentDirectory");
        }
    }

    public static function moveImagesForNewSlug($model, $oldSlug, $newSlug, $type)
    {
        if ($model->images()->exists()) {
            foreach ($model->images as $image) {
                // Keep the same random string and 'final' suffix if they exist
                $currentDirectory = dirname($image->large_image_path);
                $directorySuffix = str_replace("$type-images/$oldSlug", '', $currentDirectory);
                $newDirectory = "$type-images/$newSlug$directorySuffix";
                
                // Copy all image variations to new location
                Storage::disk('digitalocean')->copy(
                    "/public/$image->large_image_path",
                    "/public/$newDirectory/$newSlug.webp"
                );
                Storage::disk('digitalocean')->copy(
                    "/public/" . preg_replace('/\.webp$/', '.jpg', $image->large_image_path),
                    "/public/$newDirectory/$newSlug.jpg"
                );
                Storage::disk('digitalocean')->copy(
                    "/public/$image->thumb_image_path",
                    "/public/$newDirectory/$newSlug-thumb.webp"
                );
                Storage::disk('digitalocean')->copy(
                    "/public/" . preg_replace('/\.webp$/', '.jpg', $image->thumb_image_path),
                    "/public/$newDirectory/$newSlug-thumb.jpg"
                );

                // Update image paths in database
                $image->update([
                    'large_image_path' => "$newDirectory/$newSlug.webp",
                    'thumb_image_path' => "$newDirectory/$newSlug-thumb.webp",
                ]);

                // Update model's image paths if columns exist
                $table = $model->getTable();
                $hasImageColumns = \Schema::hasColumns($table, ['largeImagePath', 'thumbImagePath']);
                if ($hasImageColumns) {
                    $model->largeImagePath = "$newDirectory/$newSlug.webp";
                    $model->thumbImagePath = "$newDirectory/$newSlug-thumb.webp";
                    $model->save();
                }

                // Delete the original directory
                Storage::disk('digitalocean')->deleteDirectory("/public/$currentDirectory");
            }
        }
    }

}