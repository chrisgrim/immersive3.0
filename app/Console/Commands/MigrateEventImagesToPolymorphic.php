<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\Image;

class MigrateEventImagesToPolymorphic extends Command
{
    protected $signature = 'migrate:event-images';
    protected $description = 'Migrate event images to polymorphic images table';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Retrieve all events
        $events = Event::all();

        foreach ($events as $event) {
            if ($event->largeImagePath && $event->thumbImagePath) {
                // Create a new polymorphic image record
                Image::create([
                    'imageable_id' => $event->id,
                    'imageable_type' => Event::class,
                    'large_image_path' => $event->largeImagePath,
                    'thumb_image_path' => $event->thumbImagePath,
                ]);

                $this->info("Migrated images for event ID: {$event->id}");
            }
        }

        $this->info('All event images have been migrated.');
    }
}
