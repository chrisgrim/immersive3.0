<?php

namespace App\Actions\Search;

/**
 * The other half of a SavedSearch's round trip — turns its stored criteria
 * back into the exact query string /index/search already knows how to read
 * (see SearchStore.vue's initializeFromUrl() and ListingsController), so
 * "run this saved search" is just a link, not a second thing to keep in sync.
 */
class BuildSearchUrlAction
{
    public function handle(array $criteria): string
    {
        $params = [];

        if (! empty($criteria['city'])) {
            $params['city'] = $criteria['city'];
        }

        if (isset($criteria['lat'])) {
            $params['lat'] = $criteria['lat'];
        }

        if (isset($criteria['lng'])) {
            $params['lng'] = $criteria['lng'];
        }

        if (! empty($criteria['searchType'])) {
            $params['searchType'] = $criteria['searchType'];
        }

        if (! empty($criteria['remoteLocation'])) {
            $params['remoteLocation'] = $criteria['remoteLocation'];
        }

        // Always explicit ('true' or 'false'), not just when truthy — a
        // plain (non-map-driven) city search still saves live: false, but
        // ListingsController::index() picks the map view via
        // isset($request->live), not its value. Dropping the param
        // entirely for a false value (the old behavior) made a replayed
        // city search silently lose the map, landing on the plain grid
        // view instead of the one it was originally saved from.
        if (! empty($criteria['city']) || ! empty($criteria['live'])) {
            $params['live'] = ! empty($criteria['live']) ? 'true' : 'false';
        }

        // A "custom map search" (dragging/zooming the map to an area rather
        // than picking a city) — see ListingsController::buildMapBoundaryFilter(),
        // which only applies the bounding-box filter when live is exactly
        // 'true' and all four of these are present.
        if (isset($criteria['NElat'], $criteria['NElng'], $criteria['SWlat'], $criteria['SWlng'])) {
            $params['NElat'] = $criteria['NElat'];
            $params['NElng'] = $criteria['NElng'];
            $params['SWlat'] = $criteria['SWlat'];
            $params['SWlng'] = $criteria['SWlng'];
            $params['live'] = 'true';
        }

        if (! empty($criteria['start'])) {
            $params['start'] = $criteria['start'];
        }

        if (! empty($criteria['end'])) {
            $params['end'] = $criteria['end'];
        }

        if (! empty($criteria['categories'])) {
            $params['category'] = implode(',', $criteria['categories']);
        }

        if (! empty($criteria['tags'])) {
            $params['tag'] = implode(',', $criteria['tags']);
        }

        if (! empty($criteria['price'][0])) {
            $params['price0'] = $criteria['price'][0];
        }

        if (! empty($criteria['price'][1])) {
            $params['price1'] = $criteria['price'][1];
        }

        return '/index/search'.($params ? '?'.http_build_query($params) : '');
    }
}
