/**
 * Specs for the SearchStore reactive singleton (resources/js/Stores/SearchStore.vue).
 *
 * SearchStore imports `axios` directly (`import axios from 'axios'`), so fetchResults
 * uses the real axios module — not window.axios. We vi.mock('axios') locally so we can
 * intercept axios.get and drive resolve/reject paths.
 *
 * The store is exported as a single instance (`export default new SearchStore()`), so its
 * `state` persists across tests within this file. We reset state in beforeEach to keep
 * tests isolated.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest';

// axios is imported directly inside SearchStore.vue; mock the module so fetchResults
// hits our fake client instead of the real one.
vi.mock('axios', () => {
    const get = vi.fn(() => Promise.resolve({ data: {} }));
    return {
        default: { get },
    };
});

import axios from 'axios';
import searchStore from '@/Stores/SearchStore.vue';

// Snapshot of a pristine store state to restore before each test.
function freshState() {
    return {
        events: {
            data: [],
            total: 0,
            current_page: 1,
            per_page: 20,
            from: null,
            to: null,
            last_page: 1,
        },
        filters: {
            categories: [],
            tags: [],
            price: [0, null],
            maxPrice: null,
            searchingByPrice: false,
            atHome: false,
        },
        location: {
            city: null,
            lat: null,
            lng: null,
            live: false,
        },
        dates: {
            start: null,
            end: null,
        },
        loading: false,
    };
}

// Replace window.location with a controllable stub so initializeFromUrl can read .search.
function setSearch(search) {
    delete window.location;
    window.location = { search };
}

beforeEach(() => {
    // Reset the singleton's reactive state in place (it's the same object across imports).
    Object.assign(searchStore.state, freshState());
    // Clear any leftover listeners between tests.
    searchStore.listeners.clear();
    // Default location with no query string.
    setSearch('');
    axios.get.mockReset();
    axios.get.mockResolvedValue({ data: {} });
});

describe('SearchStore — initializeFromUrl', () => {
    it('parses city, stripping a trailing ", USA"', () => {
        setSearch('?city=Portland%2C%20USA');

        const result = searchStore.initializeFromUrl();

        expect(searchStore.state.location.city).toBe('Portland');
        expect(result.location.city).toBe('Portland');
    });

    it('keeps non-US city names intact', () => {
        setSearch('?city=Paris%2C%20France');

        searchStore.initializeFromUrl();

        expect(searchStore.state.location.city).toBe('Paris, France');
    });

    it('parses lat/lng as floats and live as a boolean', () => {
        setSearch('?lat=45.52&lng=-122.68&live=true');

        searchStore.initializeFromUrl();

        expect(searchStore.state.location.lat).toBe(45.52);
        expect(searchStore.state.location.lng).toBe(-122.68);
        expect(searchStore.state.location.live).toBe(true);
    });

    it('leaves lat/lng null and live false when absent', () => {
        setSearch('');

        searchStore.initializeFromUrl();

        expect(searchStore.state.location.lat).toBeNull();
        expect(searchStore.state.location.lng).toBeNull();
        expect(searchStore.state.location.live).toBe(false);
    });

    it('parses category and tag CSV params into number arrays', () => {
        setSearch('?category=1,2,3&tag=10,20');

        searchStore.initializeFromUrl();

        expect(searchStore.state.filters.categories).toEqual([1, 2, 3]);
        expect(searchStore.state.filters.tags).toEqual([10, 20]);
    });

    it('parses price0/price1 and flags searchingByPrice when a price param is present', () => {
        setSearch('?price0=25&price1=100');

        searchStore.initializeFromUrl();

        expect(searchStore.state.filters.price).toEqual([25, 100]);
        expect(searchStore.state.filters.searchingByPrice).toBe(true);
    });

    it('falls back to maxPrice for the upper price bound when price1 is absent', () => {
        setSearch('?price0=10');

        searchStore.initializeFromUrl(undefined, 250);

        expect(searchStore.state.filters.price).toEqual([10, 250]);
        expect(searchStore.state.filters.maxPrice).toBe(250);
        expect(searchStore.state.filters.searchingByPrice).toBe(true);
    });

    it('leaves searchingByPrice false when no price params are present', () => {
        setSearch('?city=Chicago');

        searchStore.initializeFromUrl();

        expect(searchStore.state.filters.searchingByPrice).toBe(false);
    });

    it('parses start/end dates', () => {
        setSearch('?start=2026-06-01&end=2026-06-30');

        searchStore.initializeFromUrl();

        expect(searchStore.state.dates.start).toBe('2026-06-01');
        expect(searchStore.state.dates.end).toBe('2026-06-30');
    });

    it('sets atHome when searchType=atHome', () => {
        setSearch('?searchType=atHome');

        searchStore.initializeFromUrl();

        expect(searchStore.state.filters.atHome).toBe(true);
    });

    it('seeds events from the searchedEvents argument', () => {
        setSearch('');
        const searchedEvents = {
            data: [{ id: 1 }, { id: 2 }],
            total: 2,
            current_page: 1,
            last_page: 1,
            from: 1,
            to: 2,
            per_page: 15,
        };

        searchStore.initializeFromUrl(searchedEvents);

        expect(searchStore.state.events.data).toEqual([{ id: 1 }, { id: 2 }]);
        expect(searchStore.state.events.total).toBe(2);
        expect(searchStore.state.events.per_page).toBe(15);
    });
});

describe('SearchStore — updateState', () => {
    it('merges new data into state', () => {
        searchStore.updateState({
            dates: { start: '2026-07-01', end: '2026-07-15' },
        });

        expect(searchStore.state.dates.start).toBe('2026-07-01');
        expect(searchStore.state.dates.end).toBe('2026-07-15');
    });

    it('preserves existing location fields not present in the update', () => {
        searchStore.updateState({ location: { city: 'Seattle', lat: 47.6 } });
        // lng/live were not provided, so they retain prior (default) values.
        expect(searchStore.state.location.city).toBe('Seattle');
        expect(searchStore.state.location.lat).toBe(47.6);
        expect(searchStore.state.location.lng).toBeNull();
        expect(searchStore.state.location.live).toBe(false);
    });

    it('notifies all subscribers with the current state', () => {
        const cb = vi.fn();
        searchStore.subscribe(cb);
        cb.mockClear(); // ignore the immediate fire from subscribe

        searchStore.updateState({ dates: { start: '2026-08-01' } });

        expect(cb).toHaveBeenCalledTimes(1);
        expect(cb).toHaveBeenCalledWith(searchStore.state);
    });
});

describe('SearchStore — subscribe / unsubscribe', () => {
    it('fires the callback immediately with current state on subscribe', () => {
        const cb = vi.fn();

        searchStore.subscribe(cb);

        expect(cb).toHaveBeenCalledTimes(1);
        expect(cb).toHaveBeenCalledWith(searchStore.state);
    });

    it('returns an unsubscribe fn that stops further notifications', () => {
        const cb = vi.fn();
        const unsubscribe = searchStore.subscribe(cb);
        cb.mockClear();

        unsubscribe();
        searchStore.updateState({ dates: { start: '2026-09-01' } });

        expect(cb).not.toHaveBeenCalled();
    });

    it('dedupes the same callback via the Set', () => {
        const cb = vi.fn();
        searchStore.subscribe(cb);
        searchStore.subscribe(cb);
        cb.mockClear();

        searchStore.updateState({ dates: { start: '2026-10-01' } });

        // Set dedupes, so the listener fires only once per update.
        expect(cb).toHaveBeenCalledTimes(1);
    });
});

describe('SearchStore — setLoading', () => {
    it('toggles loading and notifies subscribers', () => {
        const cb = vi.fn();
        searchStore.subscribe(cb);
        cb.mockClear();

        searchStore.setLoading(true);
        expect(searchStore.state.loading).toBe(true);
        expect(cb).toHaveBeenCalledTimes(1);

        searchStore.setLoading(false);
        expect(searchStore.state.loading).toBe(false);
        expect(cb).toHaveBeenCalledTimes(2);
    });
});

describe('SearchStore — fetchResults', () => {
    it('calls axios.get with the search endpoint and query string', async () => {
        axios.get.mockResolvedValue({ data: { data: [], maxPrice: 0 } });

        await searchStore.fetchResults('city=Portland&live=true');

        expect(axios.get).toHaveBeenCalledTimes(1);
        expect(axios.get).toHaveBeenCalledWith('/api/index/search?city=Portland&live=true');
    });

    it('updates state from the response and strips ", USA" from the returned city', async () => {
        const payload = {
            current_page: 2,
            data: [{ id: 9 }],
            from: 21,
            last_page: 5,
            per_page: 20,
            to: 40,
            total: 100,
            city: 'Austin, USA',
            lat: 30.27,
            lng: -97.74,
            live: false,
            maxPrice: 500,
        };
        axios.get.mockResolvedValue({ data: payload });

        const returned = await searchStore.fetchResults('city=Austin');

        expect(searchStore.state.events.data).toEqual([{ id: 9 }]);
        expect(searchStore.state.events.total).toBe(100);
        expect(searchStore.state.events.current_page).toBe(2);
        expect(searchStore.state.location.city).toBe('Austin');
        expect(searchStore.state.location.lat).toBe(30.27);
        // Not searching by price → price/maxPrice come straight from response.
        expect(searchStore.state.filters.price).toEqual([0, 500]);
        expect(searchStore.state.filters.maxPrice).toBe(500);
        // fetchResults returns the raw response data.
        expect(returned).toBe(payload);
    });

    it('keeps the user price ceiling (max of selected and response) when searchingByPrice', async () => {
        // Simulate a user who already narrowed the price range.
        searchStore.state.filters.searchingByPrice = true;
        searchStore.state.filters.price = [10, 300];

        axios.get.mockResolvedValue({
            data: { data: [], maxPrice: 200, city: null, lat: null, lng: null, live: false },
        });

        await searchStore.fetchResults('price0=10&price1=300');

        // Math.max(300, 200) === 300; price array is left as the user's selection.
        expect(searchStore.state.filters.maxPrice).toBe(300);
        expect(searchStore.state.filters.price).toEqual([10, 300]);
    });

    it('notifies subscribers during the fetch (loading + updateState)', async () => {
        const cb = vi.fn();
        axios.get.mockResolvedValue({ data: { data: [], maxPrice: 0 } });
        searchStore.subscribe(cb);
        cb.mockClear();

        await searchStore.fetchResults('city=Portland');

        // setLoading(true) → updateState → setLoading(false) all notify.
        expect(cb.mock.calls.length).toBeGreaterThanOrEqual(3);
    });

    it('toggles loading true then false around a successful fetch', async () => {
        const seen = [];
        axios.get.mockImplementation(() => {
            // loading should be true while the request is in flight.
            seen.push(searchStore.state.loading);
            return Promise.resolve({ data: { data: [], maxPrice: 0 } });
        });

        await searchStore.fetchResults('city=Portland');

        expect(seen).toEqual([true]);
        expect(searchStore.state.loading).toBe(false);
    });

    it('rethrows on axios rejection, logs the error, and resets loading', async () => {
        const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const boom = new Error('network down');
        axios.get.mockRejectedValue(boom);

        await expect(searchStore.fetchResults('city=Portland')).rejects.toThrow('network down');

        expect(consoleSpy).toHaveBeenCalledWith('Error fetching results:', boom);
        // finally block must reset loading even on failure.
        expect(searchStore.state.loading).toBe(false);

        consoleSpy.mockRestore();
    });
});
