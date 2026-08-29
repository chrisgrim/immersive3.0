/**
 * Which page component the server renders for a set of search params.
 *
 * ListingsController::index picks the map view for an in-person search that
 * carries live bounds, and the plain list view for everything else:
 *
 *     return $request->searchType === 'inPerson' && isset($request->live)
 *         ? view('search.location', $viewData)
 *         : view('search.all', $viewData);
 *
 * The nav's search handlers need this because an in-place re-fetch cannot
 * cross that line — whichever page component is already mounted stays
 * mounted. Switching to At Home from a location search used to render remote
 * events inside the map page's layout, beside a map of wherever the previous
 * search happened to be. A search that changes the view has to be a real
 * navigation.
 *
 * `isset($request->live)` is the server's test, so presence and not value is
 * what counts here too — handleLocationSearch legitimately sets live='false'.
 *
 * Kept in step with the controller by tests/Feature/SearchViewSelectionTest.php.
 */
export function rendersMapView(params) {
    return params.get('searchType') === 'inPerson' && params.has('live');
}
