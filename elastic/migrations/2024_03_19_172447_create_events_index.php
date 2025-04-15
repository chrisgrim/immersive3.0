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
            // Set index-level settings first
            $settings->index([
                'max_ngram_diff' => 3
            ]);
            
            // Text fields with multi-fields for different search strategies
            $mapping->text('name', [
                'fields' => [
                    'raw' => [
                        'type' => 'keyword'
                    ],
                    'ngram' => [
                        'type' => 'text',
                        'analyzer' => 'ngram_analyzer'
                    ]
                ],
                'analyzer' => 'standard_analyzer'
            ]);
            
            // Add search-as-you-type for auto-completion
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
            
            // Standard date field
            $mapping->date('published_at', [
                'format' => 'yyyy-MM-dd HH:mm:ss'
            ]);
            
            // Add sortable keyword field for published_at
            $mapping->keyword('published_at_sort');

            // Enhanced analysis settings
            $settings->analysis([
                'analyzer' => [
                    'default' => [
                        'type' => 'standard'
                    ],
                    'standard_analyzer' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'asciifolding']
                    ],
                    'ngram_analyzer' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'asciifolding', 'ngram_filter']
                    ]
                ],
                'filter' => [
                    'ngram_filter' => [
                        'type' => 'ngram',
                        'min_gram' => 2,
                        'max_gram' => 4
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