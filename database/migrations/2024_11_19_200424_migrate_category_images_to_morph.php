<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Category;
use App\Services\ImageHandler;
use Illuminate\Support\Facades\Storage;

class MigrateCategoryImagesToMorph extends Migration
{
    public function up()
    {
        // Clear existing morph images to avoid duplicates
        \DB::table('images')->where('imageable_type', 'App\Models\Category')->delete();

        $categories = Category::all();
        $migratedCount = 0;
        $errorCount = 0;

        foreach ($categories as $category) {
            // Skip if no images
            if (!$category->thumbImagePath) {
                continue;
            }

            try {
                // Add the extra category-images folder to the path
                $originalPath = $category->thumbImagePath;
                $fixedPath = "category-images/{$originalPath}";
                
                \Log::info("Category {$category->id} - {$category->name}:", [
                    'DB Path' => $originalPath,
                    'Checking Path' => "public/{$fixedPath}"
                ]);

                if (Storage::disk('digitalocean')->exists("public/{$fixedPath}")) {
                    $mainFile = Storage::disk('digitalocean')->get("public/{$fixedPath}");
                    $tempPath = storage_path('app/temp/' . basename($fixedPath));
                    
                    // Create temp directory if it doesn't exist
                    if (!Storage::exists('temp')) {
                        Storage::makeDirectory('temp');
                    }
                    
                    // Save to temporary file
                    Storage::put('temp/' . basename($fixedPath), $mainFile);
                    
                    // Create UploadedFile instance
                    $uploadedFile = new \Illuminate\Http\UploadedFile(
                        $tempPath,
                        basename($fixedPath),
                        Storage::disk('digitalocean')->mimeType("public/{$fixedPath}"),
                        null,
                        true
                    );

                    // Save using ImageHandler
                    ImageHandler::saveImage(
                        $uploadedFile,
                        $category,
                        800,
                        800,
                        'category'
                    );

                    // Cleanup temp file
                    Storage::delete('temp/' . basename($fixedPath));
                    
                    // Verify the new image was created
                    if ($category->fresh()->images()->count() > 0) {
                        $migratedCount++;
                        \Log::info("Successfully migrated images for category {$category->id} - {$category->name}");
                        
                        // Only clear old paths after successful migration
                        $category->update([
                            'thumbImagePath' => null,
                            'largeImagePath' => null
                        ]);
                    } else {
                        throw new \Exception("Failed to create new image record");
                    }
                } else {
                    \Log::warning("File not found at path: public/{$fixedPath} for category {$category->id} - {$category->name}");
                }
            } catch (\Exception $e) {
                $errorCount++;
                \Log::error("Failed to migrate images for category {$category->id} - {$category->name}: " . $e->getMessage());
                continue;
            }
        }

        // Only remove columns if migration was successful
        if ($errorCount === 0 && $migratedCount > 0) {
            \Log::info("Successfully migrated {$migratedCount} categories. Removing old columns.");
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn(['thumbImagePath', 'largeImagePath']);
            });
        } else {
            \Log::warning("Migration completed with {$migratedCount} successes and {$errorCount} errors. Old columns retained.");
            if ($errorCount > 0) {
                throw new \Exception("Migration had {$errorCount} errors. Check logs for details.");
            }
        }
    }

    public function down()
    {
        // Check if columns don't exist before adding them
        if (!Schema::hasColumn('categories', 'thumbImagePath')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('thumbImagePath')->nullable();
                $table->string('largeImagePath')->nullable();
            });
        }
    }
}