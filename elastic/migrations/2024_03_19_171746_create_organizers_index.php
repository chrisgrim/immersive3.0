<?php
declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

final class CreateOrganizersIndex implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Index::create('organizers', function (Mapping $mapping, Settings $settings) {
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
            
            $mapping->keyword('email');
            $mapping->integer('rank');
            $mapping->date('published_at');
            
            // Enhanced analysis settings
            $settings->analysis([
                'analyzer' => [
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
        Index::dropIfExists('organizers');
    }
}
