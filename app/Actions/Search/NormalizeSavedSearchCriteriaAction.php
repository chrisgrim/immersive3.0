<?php

namespace App\Actions\Search;

/**
 * Sorted/deduped category & tag ids and consistent null-vs-absent handling,
 * so two searches that are the same to a user (same filters, different array
 * order, one omits a default) fingerprint identically. Shared by
 * SaveSearchAction (auto-save, on every search) and UpdateSavedSearchAction
 * (deliberate edits from the Hub) — extracted rather than duplicated so both
 * always agree on what "the same criteria" means.
 */
class NormalizeSavedSearchCriteriaAction
{
    public function handle(array $criteria): array
    {
        $categories = collect($criteria['categories'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $tags = collect($criteria['tags'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $price = $criteria['price'] ?? null;

        return [
            'city' => $criteria['city'] ?? null,
            'lat' => isset($criteria['lat']) ? (float) $criteria['lat'] : null,
            'lng' => isset($criteria['lng']) ? (float) $criteria['lng'] : null,
            'searchType' => $criteria['searchType'] ?? null,
            'remoteLocation' => $criteria['remoteLocation'] ?? null,
            'live' => (bool) ($criteria['live'] ?? false),
            // A "custom map search" — dragging/zooming the map to an area
            // rather than picking a city — matching what ListingsController::
            // buildMapBoundaryFilter() already reads from a live results-page
            // map move (see nav-search.vue's subscribeToMapStore). Only
            // meaningful together with live=true; all four or none.
            'NElat' => isset($criteria['NElat']) ? (float) $criteria['NElat'] : null,
            'NElng' => isset($criteria['NElng']) ? (float) $criteria['NElng'] : null,
            'SWlat' => isset($criteria['SWlat']) ? (float) $criteria['SWlat'] : null,
            'SWlng' => isset($criteria['SWlng']) ? (float) $criteria['SWlng'] : null,
            'start' => $criteria['start'] ?? null,
            'end' => $criteria['end'] ?? null,
            'categories' => $categories,
            'tags' => $tags,
            'price' => is_array($price) ? [$price[0] ?? null, $price[1] ?? null] : null,
        ];
    }
}
