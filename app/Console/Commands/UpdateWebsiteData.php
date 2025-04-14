<?php

namespace App\Console\Commands;

use App\Models\Organizer;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class UpdateWebsiteData extends Command
{
    protected $signature = 'website:update-data {--reset-elastic-migrations : Reset Elasticsearch migrations}';
    protected $description = 'Run all necessary data updates across the website models';

    public function handle()
    {
        if ($this->option('reset-elastic-migrations')) {
            $this->resetElasticMigrations();
            return;
        }

        $this->updateOrganizerOwnership();
        $this->updateCategoryImages();
        $this->updateTicketNamespaces();
        $this->migrateEventVideos();
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

        // Delete existing indices
        $this->info('Deleting existing Elasticsearch indices...');
        $indices = ['events', 'organizers', 'genres', 'categories'];
        
        foreach ($indices as $index) {
            $this->info("Deleting index: {$index}");
            try {
                $ch = curl_init("localhost:9200/{$index}");
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch);
                curl_close($ch);
                $this->info("Result: {$result}");
            } catch (\Exception $e) {
                $this->error("Error deleting index {$index}: " . $e->getMessage());
            }
        }

        // Run the migrations
        $this->info('Running Elasticsearch migrations...');
        Artisan::call('elastic:migrate');
        $this->info(Artisan::output());

        // Reimport models
        $this->info('Reimporting models to Elasticsearch...');
        $models = [
            'App\\Models\\Event',
            'App\\Models\\Organizer',
            'App\\Models\\Genre',
            'App\\Models\\Category'
        ];

        foreach ($models as $model) {
            $this->info("Importing {$model}...");
            Artisan::call('scout:import', ['model' => $model]);
            $this->info(Artisan::output());
        }

        $this->info('Elasticsearch migrations reset complete!');
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
}