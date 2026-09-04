/**
 * Specs for PageComponents/Nav/nav-search-mobile.vue
 *
 * The mobile nav's search shell — untested until now despite being on every
 * page. The behavior guarded here is handleSearch()/handleAtHomeSearch():
 * which URL each mode builds, and whether the search gets auto-saved to
 * "Recent searches".
 *
 * That auto-save is a live-reported regression (mobile searches never showed
 * up in Recent searches because the desktop call was never ported over), and
 * it is deliberately AWAITED before navigating — an un-awaited POST gets
 * cancelled mid-flight by the browser when window.location.href changes. The
 * ordering assertion below is what keeps that from being "optimized" back
 * into a fire-and-forget call.
 *
 * See at-home-search-mobile.spec.js for why axios/useSavedSearches are
 * mocked as modules rather than via window.axios.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { vi } from 'vitest';
import NavSearchMobile from '@/PageComponents/Nav/nav-search-mobile.vue';
import SearchStore from '@/Stores/SearchStore.vue';
import MapStore from '@/Stores/MapStore.vue';
import axios from 'axios';
import { saveSearch } from '@/composables/useSavedSearches';

vi.mock('axios', () => ({ default: { get: vi.fn(() => Promise.resolve({ data: [] })), post: vi.fn(() => Promise.resolve({ data: {} })) } }));
vi.mock('@/composables/useSavedSearches', () => ({ saveSearch: vi.fn(() => Promise.resolve()) }));

let navigatedTo;

function stubLocation(search = '') {
    navigatedTo = null;
    Object.defineProperty(window, 'location', {
        configurable: true,
        writable: true,
        value: {
            pathname: '/index/search',
            search,
            get href() { return navigatedTo; },
            set href(v) { navigatedTo = v; },
        },
    });
}

function setState({ city = null, lat = null, lng = null, start = null, end = null, atHome = false } = {}) {
    Object.assign(SearchStore.state.location, { city, lat, lng, live: false });
    Object.assign(SearchStore.state.dates, { start, end });
    Object.assign(SearchStore.state.filters, {
        categories: [], tags: [], price: [0, 100], maxPrice: 100, atHome, remoteLocation: null,
    });
}

// Mount BEFORE seeding the store: this component's onMounted() calls
// SearchStore.initializeFromUrl(), which rebuilds location/dates from the
// URL and silently discards anything set beforehand.
function mountNav(state = {}) {
    const wrapper = mount(NavSearchMobile, {
        global: {
            stubs: {
                SearchLocation: true, SearchEvent: true, SearchAtHome: true, Filters: true,
                VueDatePicker: true,
            },
        },
    });
    setState(state);
    return wrapper;
}

const params = () => new URLSearchParams(navigatedTo.split('?')[1]);

beforeEach(() => {
    stubLocation();
    saveSearch.mockClear();
    window.Laravel = { user: { id: 1 } };
});

describe('nav-search-mobile.vue', () => {
    describe('handleSearch — in-person', () => {
        it('builds an inPerson url with coordinates', async () => {
            const wrapper = mountNav({ city: 'Los Angeles, CA', lat: 34.05, lng: -118.24 });
            await wrapper.vm.handleSearch();
            await flushPromises();

            expect(params().get('city')).toBe('Los Angeles, CA');
            expect(params().get('lat')).toBe('34.05');
            expect(params().get('searchType')).toBe('inPerson');
            expect(params().get('live')).toBe('false');
        });

        it('auto-saves the search to Recent searches', async () => {
            const wrapper = mountNav({ city: 'Los Angeles, CA', lat: 34.05, lng: -118.24 });
            await wrapper.vm.handleSearch();
            await flushPromises();

            expect(saveSearch).toHaveBeenCalledWith('Los Angeles, CA', expect.objectContaining({
                city: 'Los Angeles, CA', searchType: 'inPerson', live: false,
            }));
        });

        it('finishes the auto-save BEFORE navigating away', async () => {
            // An un-awaited POST is cancelled by the browser when the
            // location changes, which is what silently dropped these.
            let settled = false;
            saveSearch.mockImplementationOnce(() => Promise.resolve().then(() => { settled = true; }));
            const wrapper = mountNav({ city: 'Los Angeles, CA', lat: 34.05, lng: -118.24 });

            const done = wrapper.vm.handleSearch();
            expect(navigatedTo).toBeNull(); // not navigated yet
            await done;

            expect(settled).toBe(true);
            expect(navigatedTo).toContain('/index/search?');
        });

        it('omits lat/lng but still searches when coordinates are missing', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            const wrapper = mountNav({ city: 'Nowhere', lat: null, lng: null });
            await wrapper.vm.handleSearch();
            await flushPromises();

            expect(params().get('city')).toBe('Nowhere');
            expect(params().has('lat')).toBe(false);
        });
    });

    describe('handleSearch — other modes', () => {
        it('clears location params and skips the auto-save in remote mode', async () => {
            const wrapper = mountNav({ city: 'Los Angeles, CA', lat: 34.05, lng: -118.24, atHome: true });
            await wrapper.vm.handleSearch();
            await flushPromises();

            expect(params().get('searchType')).toBe('atHome');
            expect(params().has('city')).toBe(false);
            expect(saveSearch).not.toHaveBeenCalled();
        });

        it('uses searchType=null for a date-only search', async () => {
            const wrapper = mountNav({ start: '2026-09-01 00:00:00', end: '2026-09-03 00:00:00' });
            await wrapper.vm.handleSearch();
            await flushPromises();

            expect(params().get('searchType')).toBe('null');
            expect(params().get('start')).toBe('2026-09-01 00:00:00');
        });

        it('does not navigate at all with neither a location nor dates', async () => {
            const wrapper = mountNav({});
            await wrapper.vm.handleSearch();
            await flushPromises();

            expect(navigatedTo).toBeNull();
            expect(saveSearch).not.toHaveBeenCalled();
        });
    });

    describe('handleAtHomeSearch', () => {
        it('includes the remoteLocation slug when a type was picked', async () => {
            const wrapper = mountNav({});
            wrapper.vm.handleAtHomeSearch({
                remoteLocation: 'zoom', remoteLocationName: 'Zoom',
                dates: { start: null, end: null },
            });
            await flushPromises();

            expect(params().get('searchType')).toBe('atHome');
            expect(params().get('remoteLocation')).toBe('zoom');
        });

        it('omits remoteLocation entirely for the "All At Home" option', async () => {
            // Its slug is null by design — the absence of the param IS the
            // "every platform" search. A stray empty remoteLocation= would
            // filter the results to nothing.
            const wrapper = mountNav({});
            wrapper.vm.handleAtHomeSearch({
                remoteLocation: null, remoteLocationName: null,
                dates: { start: null, end: null },
            });
            await flushPromises();

            expect(params().get('searchType')).toBe('atHome');
            expect(params().has('remoteLocation')).toBe(false);
        });

        it('leaves the auto-save to the child component', async () => {
            const wrapper = mountNav({});
            wrapper.vm.handleAtHomeSearch({
                remoteLocation: 'zoom', remoteLocationName: 'Zoom',
                dates: { start: null, end: null },
            });
            await flushPromises();

            expect(saveSearch).not.toHaveBeenCalled();
        });
    });

    describe('a new search', () => {
        it('drops the depth the previous search had opened', async () => {
            // page=N means N pages are open; a fresh search must start closed.
            stubLocation('?city=Los+Angeles&lat=34.05&lng=-118.24&searchType=inPerson&live=false&page=3');
            const wrapper = mountNav({ city: 'San Diego, CA', lat: 32.71, lng: -117.16 });
            await wrapper.vm.handleSearch();
            await flushPromises();

            expect(params().get('city')).toBe('San Diego, CA');
            expect(params().has('page')).toBe(false);
        });
    });

    describe('map moves', () => {
        // A new viewport is a new result set. The desktop nav resets the
        // page on a pan; the mobile one didn't, so panning from page 2 into
        // a sparser area returned a real total with an empty page — and,
        // now that the map draws every match, a full map over an empty list.
        it('resets to page 1 when the map is panned', async () => {
            stubLocation('?city=Los+Angeles&lat=34.05&lng=-118.24&searchType=inPerson&live=false&page=2');
            const push = vi.spyOn(window.history, 'pushState').mockImplementation(() => {});
            axios.get.mockClear();
            const wrapper = mountNav({ city: 'Los Angeles', lat: 34.05, lng: -118.24 });

            MapStore.boundsUpdate(
                { _northEast: { lat: 34.2, lng: -118.1 }, _southWest: { lat: 33.9, lng: -118.5 } },
                { lat: 34.05, lng: -118.3 },
            );
            await flushPromises();

            const pushed = new URLSearchParams(push.mock.calls.at(-1)[2].split('?')[1]);
            expect(pushed.get('page')).toBe('1');
            expect(pushed.get('live')).toBe('true');
            expect(axios.get).toHaveBeenCalledWith(expect.stringContaining('page=1'), expect.anything());

            wrapper.unmount();
            push.mockRestore();
        });
    });
});
