/**
 * Specs for composables/useSearchView.js
 *
 * The nav's search handlers decided whether to redirect or re-fetch in place
 * from "did the city change" / "did the remote type change". Neither noticed
 * that the PAGE had to change: searching All At Home from a location search
 * left remoteLocation null on both sides, so the type "hadn't changed", and
 * the in-place re-fetch rendered remote events inside the map page's layout,
 * beside a map of wherever the previous search happened to be.
 *
 * This is the rule that catches it, and it mirrors ListingsController::index.
 * tests/Feature/SearchViewSelectionTest.php pins the two together.
 */
import { rendersMapView } from '@/composables/useSearchView';

const params = (query) => new URLSearchParams(query);

describe('rendersMapView', () => {
    it('is the map view for an in-person search carrying live bounds', () => {
        expect(rendersMapView(params('searchType=inPerson&live=true&city=Los+Angeles'))).toBe(true);
    });

    it('is the list view for an in-person search with no bounds', () => {
        expect(rendersMapView(params('searchType=inPerson&city=Los+Angeles'))).toBe(false);
    });

    it('is the list view for an At Home search', () => {
        expect(rendersMapView(params('searchType=atHome'))).toBe(false);
    });

    it('is the list view for an At Home search even if live lingers in the URL', () => {
        // searchType is the first half of the server's test, so a leftover
        // live param cannot drag an At Home search onto the map page.
        expect(rendersMapView(params('searchType=atHome&live=true'))).toBe(false);
    });

    it('counts live by presence, not by value', () => {
        // The server's test is isset($request->live). handleLocationSearch
        // legitimately sets live='false' when clearing map bounds, and that
        // still renders the map page — reading it as a boolean would have
        // this disagree with the server exactly there.
        expect(rendersMapView(params('searchType=inPerson&live=false'))).toBe(true);
    });

    it('is the list view for an empty search', () => {
        expect(rendersMapView(params(''))).toBe(false);
    });

    it('detects the crossing that caused the bug', () => {
        // A location map search → All At Home: no city, no remote type on
        // either side, but the page has to change.
        const before = params('searchType=inPerson&live=true&city=Los+Angeles&lat=34&lng=-118');
        const after = params('searchType=atHome&page=1');

        expect(rendersMapView(before)).not.toBe(rendersMapView(after));
    });
});
