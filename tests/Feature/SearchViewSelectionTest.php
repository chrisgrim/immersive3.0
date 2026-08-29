<?php

/**
 * The search page's view choice lives in two places by necessity: the server
 * picks the Blade view, and the nav's JS has to know which one it picked so a
 * search that changes the page does a real navigation instead of an in-place
 * re-fetch. When they disagreed, choosing "All At Home" from a location
 * search re-fetched in place and rendered remote events inside the map page's
 * layout, next to a map of the previous search's city.
 *
 * A JS constant can't import a PHP one, so these assert the controller still
 * decides the way composables/useSearchView.js assumes it does. The drift has
 * to fail here before it can reach a user.
 */
function listingsControllerSource(): string
{
    return file_get_contents(app_path('Http/Controllers/Search/ListingsController.php'));
}

function searchViewComposableSource(): string
{
    return file_get_contents(resource_path('js/composables/useSearchView.js'));
}

test('the controller still picks the view on searchType plus live', function () {
    // Whitespace-tolerant, but the two operands and the two views are fixed:
    // if either side of the condition changes, useSearchView.js has to change
    // with it.
    expect(listingsControllerSource())->toMatch(
        '/\$request->searchType\s*===\s*\'inPerson\'\s*&&\s*isset\(\$request->live\)\s*\R?\s*\?\s*view\(\'search\.location\'/'
    );
});

test('the list view is the other branch, not a third case', function () {
    expect(listingsControllerSource())->toMatch('/:\s*view\(\'search\.all\'/');
});

test('the composable mirrors both halves of that condition', function () {
    expect(searchViewComposableSource())
        // searchType must be compared to the same literal...
        ->toContain("params.get('searchType') === 'inPerson'")
        // ...and live tested for PRESENCE, matching isset() rather than a
        // truthiness check — handleLocationSearch sets live='false', which
        // isset() still considers set.
        ->toContain("params.has('live')");
});

test('the geo filter uses the same condition, so the two cannot drift apart', function () {
    // applyGeoFilter is derived from the identical test a few lines above the
    // view choice. If someone changes one they must change both, and this
    // fails if only one moved.
    expect(listingsControllerSource())->toMatch(
        '/\$applyGeoFilter\s*=\s*\$request->searchType\s*===\s*\'inPerson\'\s*&&\s*isset\(\$request->live\)/'
    );
});
