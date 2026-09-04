<?php

namespace Tests\Support;

use App\Models\Event;
use Elastic\Adapter\Search\SearchParameters;
use Elastic\Adapter\Search\SearchResult;
use Elastic\ScoutDriverPlus\Engine;
use Laravel\Scout\EngineManager;

/**
 * Stands in for Elasticsearch in tests that need to run a search endpoint
 * end to end.
 *
 * The test env has SCOUT_DRIVER=null, under which Event::searchQuery(...)
 * ->execute() goes through Elastic Scout Driver Plus's NullEngine and always
 * finds nothing. Fine for "does the endpoint survive", useless for "does it
 * return what it should". This engine answers every search with one canned
 * hit list, records each request's parameters so a test can assert on the
 * query itself (size, _source, filters), and no-ops indexing so model saves
 * never reach for a real cluster. Model hydration is left alone: the driver
 * resolves hits to models through the database, so the ids given to
 * install() have to exist as rows.
 */
final class FakeSearchEngine extends Engine
{
    /** @var array<int, array<string, mixed>> Every search run, as SearchParameters::toArray(), in order. */
    public array $searches = [];

    /** @var array<int, int> Ids of every model handed to update() (indexed), in order. */
    public array $indexed = [];

    /** @var array<string, mixed> */
    private array $rawResult = ['hits' => ['total' => ['value' => 0], 'hits' => []]];

    /**
     * Make every search from now on return hits for these ids, in this order.
     *
     * @param  int[]  $ids
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model  the searchable model the ids belong to
     */
    public static function install(array $ids = [], float $maxPrice = 0, string $model = Event::class): self
    {
        // The driver maps a hit back to its model by index name, so every
        // canned hit has to claim the model's index.
        $index = (new $model)->searchableAs();

        $engine = app(self::class);
        $engine->rawResult = [
            'hits' => [
                'total' => ['value' => count($ids)],
                'hits' => array_map(
                    fn ($id) => ['_index' => $index, '_id' => (string) $id, '_score' => 1.0, '_source' => []],
                    array_values($ids),
                ),
            ],
            'aggregations' => ['max_price' => ['value' => $maxPrice]],
        ];

        $manager = resolve(EngineManager::class);
        // Any model save resolves the 'null' driver, and a cached instance
        // would ignore the creator registered below.
        $manager->forgetDrivers();
        $manager->extend('null', fn () => $engine);

        return $engine;
    }

    public function searchWithParameters(SearchParameters $searchParameters): SearchResult
    {
        $params = $searchParameters->toArray();
        $this->searches[] = $params;

        // Honour from/size the way the real thing does, so a paginated
        // request gets that page's slice and `total` still counts everything.
        $hits = $this->rawResult['hits']['hits'];
        $from = $params['body']['from'] ?? 0;
        $size = $params['body']['size'] ?? count($hits);
        $result = $this->rawResult;
        $result['hits']['hits'] = array_slice($hits, $from, $size);

        return new SearchResult($result);
    }

    /**
     * The searches that asked for ids only (`_source: false`) — which is how
     * ListingsController::mapPins() asks for the map's pins.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pinSearches(): array
    {
        return array_values(array_filter(
            $this->searches,
            fn (array $search) => ($search['body']['_source'] ?? null) === false,
        ));
    }

    public function update($models)
    {
        $this->indexed = array_merge($this->indexed, $models->pluck('id')->all());
    }

    public function delete($models) {}

    public function flush($model) {}
}
