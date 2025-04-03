<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

class ElasticDeleteIndices extends Command
{
    protected $signature = 'elastic:delete-indices';
    protected $description = 'Delete all Elasticsearch indices';

    public function handle()
    {
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

        $this->info('All indices have been deleted!');
    }
} 