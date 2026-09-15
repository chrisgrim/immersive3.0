<?php

use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Organizer;

/**
 * The migration that adds locations.region_long ran on prod in June 2025 but
 * its file was deleted from the repo (commit b75449e) and only restored on
 * 2026-09-14, so fresh installs and the test database silently lacked the
 * column the model, validator and MCP schema all accept. This pins it.
 */
test('locations persist region_long on a fresh schema', function () {
    $event = Event::factory()->create([
        'organizer_id' => Organizer::factory()->create()->id,
    ]);

    $location = Location::create([
        'event_id' => $event->id,
        'city' => 'Los Angeles',
        'region' => 'CA',
        'region_long' => 'California',
        'country' => 'US',
        'latitude' => 34.05,
        'longitude' => -118.24,
    ]);

    expect(Location::find($location->id)->region_long)->toBe('California');
});
