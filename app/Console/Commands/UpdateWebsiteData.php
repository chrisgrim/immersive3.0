<?php

namespace App\Console\Commands;

use App\Models\Organizer;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\CachedDataController;

class UpdateWebsiteData extends Command
{
    protected $signature = 'website:update-data {--reset-elastic-migrations : Reset Elasticsearch migrations} {--rebuild-cache : Rebuild Redis cache for categories and genres} {--refactor-categories : Refactor categories to remove redundancy, correctly assign attendance types, and migrate events}';
    protected $description = 'Run all necessary data updates across the website models';

    public function handle()
    {
        if ($this->option('reset-elastic-migrations')) {
            $this->resetElasticMigrations();
            return;
        }

        if ($this->option('rebuild-cache')) {
            $this->rebuildRedisCache();
            return;
        }

        if ($this->option('refactor-categories')) {
            $this->refactorCategories();
            return;
        }

        $this->updateOrganizerOwnership();
        $this->updateCategoryImages();
        $this->updateTicketNamespaces();
        $this->migrateEventVideos();
        $this->updateInvalidCardTypes();
        $this->updateUserCurrentTeamIds();
        $this->syncEventAttendanceTypes();
    }

    private function resetElasticMigrations()
    {
        $this->info('Resetting Elasticsearch migrations...');

        // Get the migration table name from config
        $table = config('elastic.migrations.database.table', 'elastic_migrations');

        // Check if table exists
        if (!Schema::hasTable($table)) {
            $this->error("Migrations table '{$table}' does not exist!");
            return;
        }

        // Clear all migrations from the database
        $count = DB::table($table)->count();
        DB::table($table)->truncate();
        $this->info("Cleared {$count} migrations from the database.");

        // Delete existing indices directly with curl
        $this->info('Deleting Elasticsearch indices directly with curl...');
        
        // List of indices to make sure are deleted
        $indices = ['events', 'organizers', 'genres', 'categories', 'users', 'shelves'];
        
        foreach ($indices as $index) {
            $this->info("Deleting index: {$index}");
            $command = "curl -X DELETE 'localhost:9200/{$index}?ignore_unavailable=true'";
            passthru($command);
            echo "\n";
            sleep(1); // 1 second delay
        }
        
        // Double-check the genres index specifically
        $this->info("Extra check for genres index:");
        passthru("curl -X GET 'localhost:9200/genres?pretty'");
        echo "\n";
        
        $this->info("Force delete genres index:");
        passthru("curl -X DELETE 'localhost:9200/genres?ignore_unavailable=true'");
        echo "\n";
        sleep(2); // 2 second delay
        
        // Insert migration records manually
        $this->info('Manually inserting Elasticsearch migration records...');
        $table = config('elastic.migrations.database.table', 'elastic_migrations');
        
        // First, let's double check what the table structure is
        $this->info("Checking elastic_migrations table structure");
        $columns = DB::getSchemaBuilder()->getColumnListing($table);
        $this->info("Table columns: " . implode(', ', $columns));
        
        // Define the Event and Organizer migration names
        $migrationNames = [
            '2024_03_19_171746_create_organizers_index',
            '2024_03_19_172447_create_events_index'
        ];
        
        // Insert the migrations into the table with the correct structure
        foreach ($migrationNames as $migration) {
            $this->info("Inserting migration record: {$migration}");
            
            // Check if the table has a batch column
            if (in_array('batch', $columns)) {
                // Get next batch number
                $batch = DB::table($table)->max('batch') + 1;
                
                // Insert with batch number
                DB::table($table)->insert([
                    'migration' => $migration,
                    'batch' => $batch
                ]);
            } else {
                // Basic insert without batch
                DB::table($table)->insert([
                    'migration' => $migration
                ]);
            }
        }
        
        // Now manually create the indices using the migration files
        $this->info('Creating Elasticsearch indices directly...');
        
        // Define the migrations to run
        $filesToRun = [
            base_path('elastic/migrations/2024_03_19_171746_create_organizers_index.php'),
            base_path('elastic/migrations/2024_03_19_172447_create_events_index.php')
        ];
        
        // Check each file exists
        foreach ($filesToRun as $file) {
            if (!file_exists($file)) {
                $this->error("Migration file does not exist: {$file}");
                continue;
            }
            
            $this->info("Running migration file: " . basename($file));
            
            // Require the file and execute it
            try {
                require_once $file;
                $className = $this->getClassNameFromFile($file);
                
                if (class_exists($className)) {
                    $migration = new $className();
                    $migration->up();
                    $this->info("Successfully created index via {$className}");
                } else {
                    $this->error("Class {$className} not found in {$file}");
                }
            } catch (\Exception $e) {
                $this->error("Error executing migration {$file}: " . $e->getMessage());
            }
            
            sleep(1);
        }

        // Reimport only the needed models
        $this->info('Reimporting only necessary models to Elasticsearch...');
        $models = [
            'App\\Models\\Event',
            'App\\Models\\Organizer'
        ];

        foreach ($models as $model) {
            $this->info("Importing {$model}...");
            Artisan::call('scout:import', ['model' => $model]);
            $this->info(Artisan::output());
        }

        $this->info('Elasticsearch migrations reset complete!');
    }
    
    /**
     * Extract class name from migration file
     */
    private function getClassNameFromFile($filePath)
    {
        $content = file_get_contents($filePath);
        $pattern = '/class\s+([a-zA-Z0-9_]+)\s+/';
        
        if (preg_match($pattern, $content, $matches)) {
            return $matches[1];
        }
        
        // Fallback: get class name from filename
        $baseName = basename($filePath, '.php');
        $parts = explode('_', $baseName);
        array_shift($parts); // Remove timestamp
        
        return str_replace(' ', '', ucwords(implode(' ', $parts)));
    }

    private function updateOrganizerOwnership()
    {
        $this->info('Starting organizer ownership update...');
        
        $count = 0;
        $skipped = 0;
        
        $bar = $this->output->createProgressBar(Organizer::count());
        
        Organizer::whereNotNull('user_id')->chunk(100, function ($organizers) use (&$count, &$skipped, $bar) {
            foreach ($organizers as $organizer) {
                if (!$organizer->users()->where('user_id', $organizer->user_id)->exists()) {
                    $organizer->users()->attach($organizer->user_id, ['role' => 'owner']);
                    $count++;
                } else {
                    $skipped++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Completed! Added ownership relationships for {$count} organizers.");
        $this->info("Skipped {$skipped} organizers that already had relationships.");
    }

    private function updateCategoryImages()
    {
        $this->info('Starting category images update...');
        
        $count = 0;
        $skipped = 0;
        
        $bar = $this->output->createProgressBar(Category::count());
        
        Category::whereNotNull('largeImagePath')->chunk(100, function ($categories) use (&$count, &$skipped, $bar) {
            foreach ($categories as $category) {
                // Skip if category already has images relationship
                if ($category->images()->exists()) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Create new image record from largeImagePath
                $category->images()->create([
                    'large_image_path' => $category->largeImagePath,
                    'thumb_image_path' => $category->thumbImagePath,
                    'rank' => 0
                ]);
                
                $count++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Completed! Added image relationships for {$count} categories.");
        $this->info("Skipped {$skipped} categories that already had images.");
    }

    private function updateTicketNamespaces()
    {
        $this->info('Starting ticket namespaces update...');
        
        // Count how many tickets need to be updated
        $totalTickets = DB::table('tickets')
            ->where('ticket_type', 'App\\Models\\Show')
            ->count();
            
        if ($totalTickets === 0) {
            $this->info('No tickets found with namespace "App\\Models\\Show". Skipping update.');
            return;
        }
        
        $this->info("Found {$totalTickets} tickets to update.");
        
        // Create a progress bar
        $bar = $this->output->createProgressBar($totalTickets);
        
        // Process tickets in chunks to avoid memory issues
        $updatedCount = 0;
        
        DB::table('tickets')
            ->where('ticket_type', 'App\\Models\\Show')
            ->chunkById(500, function ($tickets) use (&$updatedCount, $bar) {
                $ids = $tickets->pluck('id')->toArray();
                
                // Update this chunk of tickets
                $updated = DB::table('tickets')
                    ->whereIn('id', $ids)
                    ->update(['ticket_type' => 'App\\Models\\Events\\Show']);
                
                $updatedCount += $updated;
                $bar->advance($tickets->count());
            });
            
        $bar->finish();
        
        $this->newLine();
        $this->info("Completed! Updated namespace for {$updatedCount} tickets from 'App\\Models\\Show' to 'App\\Models\\Events\\Show'.");
    }

    private function migrateEventVideos()
    {
        $this->info('Starting event videos migration...');
        
        // Count events with video data
        $totalEvents = DB::table('events')
            ->whereNotNull('video')
            ->where('video', '!=', '')
            ->count();
            
        if ($totalEvents === 0) {
            $this->info('No events found with video data. Skipping migration.');
            return;
        }
        
        $this->info("Found {$totalEvents} events with video data to migrate.");
        
        // Create a progress bar
        $bar = $this->output->createProgressBar($totalEvents);
        
        $migrated = 0;
        $skipped = 0;
        
        // Use the events query builder to get all events with video data
        DB::table('events')
            ->whereNotNull('video')
            ->where('video', '!=', '')
            ->chunkById(100, function ($events) use (&$migrated, &$skipped, $bar) {
                foreach ($events as $event) {
                    // Check if this event already has videos
                    $existingVideos = DB::table('videos')
                        ->where('videoable_id', $event->id)
                        ->where('videoable_type', 'App\\Models\\Event')
                        ->exists();
                        
                    if ($existingVideos) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }
                    
                    // Extract video URL from the video field
                    $videoUrl = trim($event->video);
                    
                    // Set platform to youtube for all videos
                    $platform = 'youtube';
                    
                    // Create a new video record
                    DB::table('videos')->insert([
                        'videoable_id' => $event->id,
                        'videoable_type' => 'App\\Models\\Event',
                        'url' => $videoUrl,
                        'platform' => $platform,
                        'rank' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    $migrated++;
                    $bar->advance();
                }
            });
            
        $bar->finish();
        
        $this->newLine();
        $this->info("Completed! Migrated {$migrated} event videos to the videos table.");
        $this->info("Skipped {$skipped} events that already had videos.");
    }

    private function rebuildRedisCache()
    {
        $this->info('Rebuilding Redis cache for categories and genres...');
        
        // Output Redis configuration
        $this->info('Redis Configuration:');
        $this->info('- Cache Driver: ' . config('cache.default'));
        $this->info('- Redis Client: ' . config('database.redis.client'));
        $this->info('- Redis Host: ' . config('database.redis.default.host'));
        $this->info('- Redis Port: ' . config('database.redis.default.port'));
        $this->info('- Redis DB (default): ' . config('database.redis.default.database'));
        $this->info('- Redis DB (cache): ' . config('database.redis.cache.database'));
        $this->info('- Cache Prefix: "' . config('cache.prefix') . '"');
        
        // First clear existing cache entries if they exist
        Cache::forget('active-categories');
        Cache::forget('active-genres');
        
        $this->info('Existing cache entries cleared.');
        
        // Rebuild the caches by calling the appropriate methods
        $cachedDataController = new CachedDataController();
        
        // Rebuild categories cache
        $categories = $cachedDataController->getActiveCategories();
        $categoryCount = $categories->count();
        $this->info("Categories cache rebuilt with {$categoryCount} active categories.");
        
        // Output the actual cache key used
        $fullCategoriesCacheKey = config('cache.prefix') . 'active-categories';
        $this->info("Full categories cache key: {$fullCategoriesCacheKey}");
        
        // Rebuild genres cache
        $genres = $cachedDataController->getActiveGenres();
        $genreCount = $genres->count();
        $this->info("Genres cache rebuilt with {$genreCount} active genres.");
        
        // Output the actual cache key used
        $fullGenresCacheKey = config('cache.prefix') . 'active-genres';
        $this->info("Full genres cache key: {$fullGenresCacheKey}");
        
        // Verify that the cache now exists
        $categoriesCached = Cache::has('active-categories');
        $genresCached = Cache::has('active-genres');
        
        if ($categoriesCached && $genresCached) {
            $this->info("Cache verification successful. Both category and genre caches exist.");
        } else {
            $this->error("Cache verification failed. Category cache exists: " . ($categoriesCached ? 'Yes' : 'No') . 
                        ", Genre cache exists: " . ($genresCached ? 'Yes' : 'No'));
        }
        
        // Show commands to check in Redis CLI
        $this->info("\nTo verify in Redis CLI, run these commands:");
        $this->info("redis-cli -n " . config('database.redis.cache.database'));
        $this->info("AUTH your_redis_password");
        $this->info("KEYS *{$fullCategoriesCacheKey}*");
        $this->info("KEYS *{$fullGenresCacheKey}*");
        
        $this->info('Redis cache rebuild complete!');
    }

    private function updateInvalidCardTypes()
    {
        $this->info('Starting invalid card types update...');
        
        // Find all cards with type 'e' but no event_id
        $invalidCards = DB::table('cards')
            ->where('type', 'e')
            ->whereNull('event_id')
            ->get();
            
        $count = $invalidCards->count();
        
        if ($count === 0) {
            $this->info('No invalid card types found. Skipping update.');
            return;
        }
        
        $this->info("Found {$count} cards with type 'e' but no event_id. Converting to type 't'...");
        
        // Create a progress bar
        $bar = $this->output->createProgressBar($count);
        
        // Get IDs to update
        $invalidCardIds = $invalidCards->pluck('id')->toArray();
        
        // Update all invalid cards
        DB::table('cards')
            ->whereIn('id', $invalidCardIds)
            ->update(['type' => 't']);
        
        // Advance progress bar to completion
        $bar->finish();
        
        $this->newLine();
        $this->info("Completed! Updated {$count} cards from type 'e' to type 't'.");
        
        // List the IDs that were changed
        $this->info("Changed card IDs: " . implode(', ', $invalidCardIds));
    }

    private function updateUserCurrentTeamIds()
    {
        $this->info('Starting current_team_id update for users...');
        
        // Get all users who have teams but null current_team_id
        $usersToUpdate = DB::table('users')
            ->whereNull('current_team_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('organizer_user')
                    ->whereRaw('users.id = organizer_user.user_id');
            })
            ->get();
            
        $count = $usersToUpdate->count();
        
        if ($count === 0) {
            $this->info('No users found with teams but missing current_team_id. Skipping update.');
            return;
        }
        
        $this->info("Found {$count} users with teams but null current_team_id.");
        
        // Create a progress bar
        $bar = $this->output->createProgressBar($count);
        $updated = 0;
        
        // Process each user
        foreach ($usersToUpdate as $user) {
            // Find the first team for this user
            $firstTeam = DB::table('organizer_user')
                ->where('organizer_user.user_id', $user->id)
                ->join('organizers', 'organizer_user.organizer_id', '=', 'organizers.id')
                ->orderBy('organizers.created_at', 'desc')
                ->select('organizers.id')
                ->first();
                
            if ($firstTeam) {
                // Update the user's current_team_id
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['current_team_id' => $firstTeam->id]);
                    
                $updated++;
            }
            
            $bar->advance();
        }
            
        $bar->finish();
        
        $this->newLine();
        $this->info("Completed! Updated current_team_id for {$updated} users.");
    }

    /**
     * Sync hasLocation with attendance_type_id for all events
     * Ensures legacy events have the correct attendance_type_id based on hasLocation
     */
    private function syncEventAttendanceTypes()
    {
        $this->info('Starting event attendance type synchronization...');
        
        // Find events where hasLocation is set but attendance_type_id is null
        $eventsToUpdateWithNull = DB::table('events')
            ->whereNotNull('hasLocation')
            ->whereNull('attendance_type_id')
            ->count();
            
        // Find events where hasLocation is null and attendance_type_id is null
        $eventsWithNullLocation = DB::table('events')
            ->whereNull('hasLocation')
            ->whereNull('attendance_type_id')
            ->count();
            
        // Find events where hasLocation and attendance_type_id mismatch
        $eventsToUpdateWithMismatch = DB::table('events')
            ->whereNotNull('hasLocation')
            ->whereNotNull('attendance_type_id')
            ->whereRaw('(hasLocation = true AND attendance_type_id != 1) OR (hasLocation = false AND attendance_type_id != 2)')
            ->count();
            
        $totalToUpdate = $eventsToUpdateWithNull + $eventsToUpdateWithMismatch + $eventsWithNullLocation;
        
        if ($totalToUpdate === 0) {
            $this->info('All events have synchronized hasLocation and attendance_type_id values. No updates needed.');
            return;
        }
        
        $this->info("Found {$eventsToUpdateWithNull} events with hasLocation set but missing attendance_type_id.");
        $this->info("Found {$eventsWithNullLocation} events with null hasLocation and missing attendance_type_id.");
        $this->info("Found {$eventsToUpdateWithMismatch} events with inconsistent hasLocation and attendance_type_id values.");
        $this->info("Total: {$totalToUpdate} events to update.");
        
        // Create a progress bar
        $bar = $this->output->createProgressBar($totalToUpdate);
        
        // First update events with null attendance_type_id
        $updatedWithLocation = DB::table('events')
            ->whereNotNull('hasLocation')
            ->whereNull('attendance_type_id')
            ->where('hasLocation', true)
            ->update(['attendance_type_id' => 1]);
            
        $bar->advance($updatedWithLocation);
        
        $updatedWithoutLocation = DB::table('events')
            ->whereNotNull('hasLocation')
            ->whereNull('attendance_type_id')
            ->where('hasLocation', false)
            ->update(['attendance_type_id' => 2]);
            
        $bar->advance($updatedWithoutLocation);
        
        // Update events where hasLocation is null
        $updatedNullLocation = DB::table('events')
            ->whereNull('hasLocation')
            ->whereNull('attendance_type_id')
            ->update([
                'hasLocation' => false,
                'attendance_type_id' => 2
            ]);
            
        $bar->advance($updatedNullLocation);
        
        // Now update events where values are inconsistent
        $updatedInPersonMismatch = DB::table('events')
            ->where('hasLocation', true)
            ->where('attendance_type_id', '!=', 1)
            ->update(['attendance_type_id' => 1]);
            
        $bar->advance($updatedInPersonMismatch);
        
        $updatedRemoteMismatch = DB::table('events')
            ->where('hasLocation', false)
            ->where('attendance_type_id', '!=', 2)
            ->update(['attendance_type_id' => 2]);
            
        $bar->advance($updatedRemoteMismatch);
        
        $bar->finish();
        
        $this->newLine();
        $totalUpdated = $updatedWithLocation + $updatedWithoutLocation + $updatedNullLocation + $updatedInPersonMismatch + $updatedRemoteMismatch;
        $this->info("Completed! Synchronized attendance types for {$totalUpdated} events:");
        $this->info("- {$updatedWithLocation} in-person events with missing attendance_type_id");
        $this->info("- {$updatedWithoutLocation} remote events with missing attendance_type_id");
        $this->info("- {$updatedNullLocation} events with null hasLocation (updated to remote)");
        $this->info("- {$updatedInPersonMismatch} in-person events with incorrect attendance_type_id");
        $this->info("- {$updatedRemoteMismatch} remote events with incorrect attendance_type_id");
    }

    /**
     * Refactor categories to remove redundancy, correctly assign attendance types, and migrate events
     */
    private function refactorCategories()
    {
        $this->info('Starting category refactoring...');
        
        // Define category mappings: [old_id => new_id]
        // This maps redundant categories to their primary category
        $categoryMappings = [
            // Festival consolidation: merge "Festival (Online)" into "Festival (In-Person)"
            // and later rename to just "Festival"
            '18' => '28', // Festival (Online) → Festival (In-Person)
        ];
        
        // Define attendance type assignments for each category
        // 1 = In-Person, 2 = Remote, [1,2] = Both
        $attendanceTypeAssignments = [
            // Categories for In-Person only (attendance_type_id = 1)
            '1' => [1],   // Immersive Theatre (In-Person)
            '7' => [1],   // Experiential Museum
            '8' => [1],   // Dining & Nightlife
            '27' => [1],  // Industry Gatherings (In-Person)
            '28' => [1],  // Festival (In-Person)
            '29' => [1],  // Themed Entertainment
            
            // Categories for Remote only (attendance_type_id = 2)
            '18' => [2],  // Festival (Online)
            
            // Categories that apply to both (attendance_type_id = 1 or 2)
            '3' => [1, 2],   // Interactive Livestream
            '5' => [1, 2],   // Escape Rooms & Games
            '6' => [1, 2],   // Alternate Reality Game/Experience
            '9' => [1, 2],   // Virtual Reality
            '10' => [1, 2],  // Immersive Audio & Podplays
            '12' => [1, 2],  // Telephone
            '13' => [1, 2],  // Classes & Workshops
            '16' => [1, 2],  // Live Action Role-Playing (LARP)
            '19' => [1, 2],  // Physical (In-a-Box) Experiences
            '20' => [1, 2],  // Magic
            '21' => [1, 2],  // Interactive Stories & Games
            '22' => [1, 2],  // Installation Art & Performances
            '23' => [1, 2],  // Virtual Worlds
            '24' => [1, 2],  // Theatrical Games
            '26' => [1, 2],  // Augmented Reality
        ];
        
        // Step 1: Update attendance types for all categories
        $this->info('Updating attendance types for categories...');
        $updatedAttendanceTypes = 0;
        
        foreach ($attendanceTypeAssignments as $categoryId => $types) {
            try {
                $updated = DB::table('categories')
                    ->where('id', $categoryId)
                    ->update(['applicable_attendance_types' => json_encode($types)]);
                
                if ($updated) {
                    $updatedAttendanceTypes++;
                    $this->info("Updated attendance types for category ID {$categoryId} to " . implode(',', $types));
                }
            } catch (\Exception $e) {
                $this->error("Error updating category {$categoryId}: " . $e->getMessage());
            }
        }
        
        // Step 2: Migrate events from redundant categories
        if (!empty($categoryMappings)) {
            $this->info('Migrating events from redundant categories...');
            $migratedEventsCount = 0;
            
            foreach ($categoryMappings as $oldCategoryId => $newCategoryId) {
                try {
                    // Count events in the old category
                    $eventCount = DB::table('events')
                        ->where('category_id', $oldCategoryId)
                        ->count();
                    
                    // Update all events from old category to new category
                    $updated = DB::table('events')
                        ->where('category_id', $oldCategoryId)
                        ->update(['category_id' => $newCategoryId]);
                    
                    $migratedEventsCount += $updated;
                    $this->info("Migrated {$updated} events from category {$oldCategoryId} to {$newCategoryId}");
                } catch (\Exception $e) {
                    $this->error("Error migrating events from category {$oldCategoryId}: " . $e->getMessage());
                }
            }
            
            // Step 3: Delete redundant categories (only after events are migrated)
            $this->info('Removing redundant categories...');
            $removedCategories = 0;
            
            foreach (array_keys($categoryMappings) as $oldCategoryId) {
                try {
                    // Check if any events still use this category
                    $eventCount = DB::table('events')
                        ->where('category_id', $oldCategoryId)
                        ->count();
                    
                    if ($eventCount > 0) {
                        $this->warn("Cannot delete category {$oldCategoryId} - still has {$eventCount} events associated");
                        continue;
                    }
                    
                    // Delete the category
                    $deleted = DB::table('categories')
                        ->where('id', $oldCategoryId)
                        ->delete();
                    
                    if ($deleted) {
                        $removedCategories++;
                        $this->info("Deleted redundant category ID {$oldCategoryId}");
                    }
                } catch (\Exception $e) {
                    $this->error("Error deleting category {$oldCategoryId}: " . $e->getMessage());
                }
            }
            
            $this->info("Completed! Migrated {$migratedEventsCount} events and removed {$removedCategories} redundant categories.");
        } else {
            $this->info("No redundant categories defined for removal.");
        }
        
        $this->info("Completed! Updated attendance types for {$updatedAttendanceTypes} categories.");
        
        // Rebuild the cache after making changes
        $this->info("Rebuilding cache to reflect category changes...");
        $this->rebuildRedisCache();
    }
}