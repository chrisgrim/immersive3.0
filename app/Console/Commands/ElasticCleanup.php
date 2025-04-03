<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Elastic\Elasticsearch\ClientBuilder;

class ElasticCleanup extends Command
{
    protected $signature = 'elastic:cleanup';
    protected $description = 'Clean up elastic migration records and indices';

    public function handle()
    {
        // Delete all records from the elastic_migrations table
        DB::table('elastic_migrations')->truncate();
        $this->info('Cleared elastic_migrations table');

        // Delete indices
        $client = ClientBuilder::create()
            ->setHosts(['localhost:9200'])
            ->build();

        $indices = ['events', 'organizers', 'genres', 'categories', 'remote_locations', 'communities', 'shelves', 'users', 'city'];

        foreach ($indices as $index) {
            try {
                $client->indices()->delete(['index' => $index]);
                $this->info("Deleted index: $index");
            } catch (\Exception $e) {
                $this->warn("Could not delete index $index: {$e->getMessage()}");
            }
        }
        
        // Run migrations fresh
        $this->call('elastic:migrate');

        $this->info('Elastic search has been cleaned up!');
    }
} 