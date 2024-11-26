<?php
declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

final class CreateEventsIndex implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Index::create('events', function (Mapping $mapping, Settings $settings) {
            // Text fields
            $mapping->search_as_you_type('name');
            
            // Keyword fields
            $mapping->keyword('showtype');
            $mapping->keyword('status');
            
            // Integer fields
            $mapping->integer('rank');
            $mapping->integer('priority');
            $mapping->integer('category_id');
            
            // Location fields
            $mapping->geoPoint('location_latlon', [
                'ignore_malformed' => true  // This helps handle malformed coordinates
            ]);
            $mapping->boolean('hasLocation');
            
            // Nested objects
            $mapping->object('priceranges', [
                'properties' => [
                    'price' => ['type' => 'integer']
                ]
            ]);
            
            $mapping->nested('shows', [  // Changed from object to nested
                'properties' => [
                    'date' => [
                        'type' => 'date',
                        'format' => 'yyyy-MM-dd HH:mm:ss'
                    ]
                ]
            ]);
            
            $mapping->object('genres', [
                'properties' => [
                    'name' => ['type' => 'keyword'],
                    'genre_id' => ['type' => 'integer']  // Added genre_id for filtering
                ]
            ]);
            
            // Date fields
            $mapping->date('closingDate', [
                'format' => 'yyyy-MM-dd HH:mm:ss'
            ]);
            
            $mapping->date('published_at', [
                'format' => 'yyyy-MM-dd HH:mm:ss'
            ]);

            // Configure some basic settings
            $settings->analysis([
                'analyzer' => [
                    'default' => [
                        'type' => 'standard'
                    ]
                ]
            ]);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Index::dropIfExists('events');
    }
}