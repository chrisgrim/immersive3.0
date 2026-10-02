<?php
declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

/**
 * closing_at: the UTC instant a run ends (Event::closingAt). closingDate is
 * a wall time in the event's own timezone, so search compared it as UTC and
 * dropped a Los Angeles run at 5pm on its last day. Adding a field to the
 * mapping needs no reindex to apply, but documents only carry it once they
 * are reindexed (scout:import); until then Event::stillRunningSearchFilter
 * falls back to closingDate for them.
 */
final class AddClosingAtToEventsIndex implements MigrationInterface
{
    public function up(): void
    {
        Index::putMapping('events', function (Mapping $mapping) {
            $mapping->date('closing_at', ['format' => 'yyyy-MM-dd HH:mm:ss']);
        });
    }

    public function down(): void
    {
        // A field cannot be removed from an Elasticsearch mapping in place.
    }
}
