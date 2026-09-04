/**
 * Specs for PageComponents/Search/location.vue and location-mobile.vue —
 * the map/list split.
 *
 * The map used to be fed `events.data`, the current page of 20, so a city
 * with 50 events showed 20 markers and a "page 2". Now the page carries a
 * separate `pins` list (every match, slimmed to what a marker draws) and the
 * map renders that. What these guard:
 *
 *   - the map gets the pins, not the page;
 *   - a page change updates the list but leaves the map instance and its
 *     pins untouched (the desktop page used to remount the whole map with
 *     :key="mapKey" on every page click, snapping it back to the initial
 *     zoom);
 *   - a new search (a store update that carries pins) does replace them.
 *
 * Leaflet can't run in jsdom, so both map components are replaced with
 * prop-recording stubs.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { nextTick, toRaw } from 'vue';
import { vi } from 'vitest';

vi.mock('axios', () => ({ default: { get: vi.fn(() => Promise.resolve({ data: {} })) } }));
vi.mock('@/PageComponents/Search/Components/map.vue', () => ({
    default: { name: 'MapStub', props: ['modelValue', 'pins'], template: '<div class="map-stub" />' },
}));
vi.mock('@/PageComponents/Search/Components/map-mobile.vue', () => ({
    default: { name: 'MapStub', props: ['modelValue', 'pins', 'fullMap', 'popupClearance'], template: '<div class="map-stub" />' },
}));
vi.mock('@/GlobalComponents/Grid/event-grid.vue', () => ({
    default: { name: 'GridStub', props: ['items', 'user', 'columns'], template: '<div class="grid-stub" />' },
}));
vi.mock('@/PageComponents/Search/Components/show-more-results.vue', () => ({
    default: { name: 'ShowMoreResults', props: ['shown', 'total', 'hasMore', 'limitReached', 'loading'], template: '<div />' },
}));
vi.mock('@/PageComponents/Search/Components/results-header.vue', () => ({
    default: { name: 'ResultsHeaderStub', props: ['total'], template: '<div />' },
}));
vi.mock('@/PageComponents/Search/Components/similar-results.vue', () => ({
    default: { name: 'SimilarResultsStub', template: '<div />' },
}));

import axios from 'axios';
import Location from '@/PageComponents/Search/location.vue';
import LocationMobile from '@/PageComponents/Search/location-mobile.vue';
import SearchStore from '@/Stores/SearchStore.vue';

const page = (ids, overrides = {}) => ({
    data: ids.map((id) => ({ id })),
    total: 40,
    current_page: 1,
    per_page: 20,
    from: 1,
    to: ids.length,
    last_page: 2,
    has_more: true,
    limit_reached: false,
    ...overrides,
});

const pinsFor = (ids) => ids.map((id) => ({
    id, slug: `event-${id}`, name: `Event ${id}`, price_range: '$10', location_latlon: { lat: 34, lon: -118 },
}));

function deferred() {
    let resolve;
    const promise = new Promise((r) => { resolve = r; });
    return { promise, resolve };
}

function mountPage(Component, initialPins) {
    return mount(Component, {
        props: { searchedEvents: page([1, 2]), user: {}, pins: initialPins },
    });
}

beforeEach(() => {
    SearchStore.state.pins = [];
    SearchStore.state.events = { data: [], total: 0, current_page: 1, per_page: 20, from: null, to: null, last_page: 1 };
    SearchStore.listeners.clear();
    axios.get.mockReset();
    window.history.pushState = vi.fn();
    window.history.replaceState = vi.fn();
    window.scrollTo = vi.fn();
});

// A Show more response: the window, and no pins (declined).
const window40 = (overrides = {}) => page([1, 2, 3, 4], { current_page: 2, to: 4, has_more: false, ...overrides });

describe.each([
    ['location.vue', Location],
    ['location-mobile.vue', LocationMobile],
])('%s — map pins', (_name, Component) => {
    it('hands the map every pin, not the page of results', () => {
        const initialPins = pinsFor([1, 2, 3, 4, 5]);
        const wrapper = mountPage(Component, initialPins);

        const map = wrapper.findComponent({ name: 'MapStub' });
        expect(toRaw(map.props('pins'))).toBe(initialPins);
        // The list is still just the page.
        expect(wrapper.findComponent({ name: 'GridStub' }).props('items')).toHaveLength(2);
    });

    it('seeds the store with the initial pins so the nav cannot wipe them', () => {
        const initialPins = pinsFor([1, 2]);
        mountPage(Component, initialPins);

        expect(toRaw(SearchStore.state.pins)).toBe(initialPins);
    });

    it('shows more without remounting the map or touching its pins', async () => {
        const initialPins = pinsFor([1, 2, 3]);
        const wrapper = mountPage(Component, initialPins);
        const mapBefore = wrapper.findComponent({ name: 'MapStub' });

        // The server answers a Show more with the whole window, pages 1..2,
        // and no pins; the list is replaced with it — not appended to.
        axios.get.mockResolvedValue({ data: window40() });
        await wrapper.vm.handleShowMore();
        await flushPromises();

        // Asked for the window, declining pins.
        const asked = new URLSearchParams(axios.get.mock.calls[0][0].split('?')[1]);
        expect(asked.get('pages')).toBe('2');
        expect(asked.has('page')).toBe(false);
        expect(asked.get('include_pins')).toBe('0');

        const mapAfter = wrapper.findComponent({ name: 'MapStub' });
        expect(mapAfter.vm).toBe(mapBefore.vm);          // same instance: no remount
        expect(toRaw(mapAfter.props('pins'))).toBe(initialPins); // same array: not re-sent
        expect(toRaw(SearchStore.state.pins)).toBe(initialPins);
        expect(wrapper.findComponent({ name: 'GridStub' }).props('items')).toEqual([{ id: 1 }, { id: 2 }, { id: 3 }, { id: 4 }]);
    });

    it('records the loaded depth in the URL without adding a history entry, and only once it landed', async () => {
        const wrapper = mountPage(Component, pinsFor([1]));
        axios.get.mockResolvedValue({ data: window40() });

        await wrapper.vm.handleShowMore();
        await flushPromises();

        expect(window.history.pushState).not.toHaveBeenCalled();
        expect(window.history.replaceState).toHaveBeenCalledTimes(1);
        const url = new URLSearchParams(window.history.replaceState.mock.calls[0][2].split('?')[1]);
        expect(url.get('page')).toBe('2');
        // The request-only params never reach the address bar.
        expect(url.has('pages')).toBe(false);
        expect(url.has('include_pins')).toBe(false);
    });

    it('leaves the URL alone when the window never lands', async () => {
        vi.spyOn(console, 'error').mockImplementation(() => {});
        const wrapper = mountPage(Component, pinsFor([1]));
        axios.get.mockRejectedValueOnce(new Error('network down'));

        await wrapper.vm.handleShowMore();
        await flushPromises();

        expect(window.history.replaceState).not.toHaveBeenCalled();
        console.error.mockRestore();
    });

    it('offers Show more only while the server says there is more', async () => {
        const wrapper = mountPage(Component, pinsFor([1]));
        expect(wrapper.findComponent({ name: 'ShowMoreResults' }).props('hasMore')).toBe(true);

        axios.get.mockResolvedValue({ data: window40() });
        await wrapper.vm.handleShowMore();
        await flushPromises();

        expect(wrapper.findComponent({ name: 'ShowMoreResults' }).props('hasMore')).toBe(false);
        expect(wrapper.findComponent({ name: 'ShowMoreResults' }).props('shown')).toBe(4);
    });

    it('passes the depth cap through so the control can explain it', async () => {
        const wrapper = mountPage(Component, pinsFor([1]));
        axios.get.mockResolvedValue({ data: window40({ has_more: false, limit_reached: true, total: 631 }) });

        await wrapper.vm.handleShowMore();
        await flushPromises();

        expect(wrapper.findComponent({ name: 'ShowMoreResults' }).props('limitReached')).toBe(true);
    });

    it('replaces the pins when a new search lands in the store', async () => {
        const wrapper = mountPage(Component, pinsFor([1, 2, 3]));
        const fresh = pinsFor([7, 8]);

        SearchStore.updateState({ pins: fresh });
        await nextTick();

        expect(toRaw(wrapper.findComponent({ name: 'MapStub' }).props('pins'))).toBe(fresh);
    });

    it('lets a map pan that lands after a slow page click win, list and pins together', async () => {
        // Show more and map pans used to be separate requests with no
        // ordering between them: a slow response arriving after a fast pan
        // overwrote the list with the OLD viewport's results while the map
        // kept the new viewport's pins. Both go through the store's
        // sequenced fetch now, so the later request wins outright.
        const wrapper = mountPage(Component, pinsFor([1, 2, 3]));
        const slowPage = deferred();
        const fastPan = deferred();
        axios.get
            .mockImplementationOnce(() => slowPage.promise)
            .mockImplementationOnce(() => fastPan.promise);

        const pageClick = wrapper.vm.handleShowMore();
        const pan = SearchStore.fetchResults('searchType=inPerson&live=true&NElat=34.2');

        const panPins = pinsFor([50]);
        fastPan.resolve({ data: { ...page([50]), pins: panPins } });
        await pan;
        slowPage.resolve({ data: window40() });
        await pageClick;
        await flushPromises();

        expect(wrapper.findComponent({ name: 'GridStub' }).props('items')).toEqual([{ id: 50 }]);
        expect(toRaw(wrapper.findComponent({ name: 'MapStub' }).props('pins'))).toBe(panPins);
        expect(SearchStore.state.loading).toBe(false);
        // The stale Show more must not stamp its depth over the pan's URL.
        expect(window.history.replaceState).not.toHaveBeenCalled();
    });
});

describe('location-mobile.vue — full-map toggle', () => {
    it('tells the map how much of the screen the list panel will take, so the popup clears it', async () => {
        // The panel's header used to overlap the popup's price line: the
        // popup sat a fixed 70px up, but the panel's on-screen top depends
        // on the nav height and the viewport. jsdom: innerHeight 768,
        // mapHeight 40% = 307.2, panel in the flow at 100 → after the slide
        // its top is 407.2, leaving 360.8px below it.
        const rect = vi.spyOn(Element.prototype, 'getBoundingClientRect').mockReturnValue({ top: 100 });
        const wrapper = mountPage(LocationMobile, pinsFor([1]));

        wrapper.vm.showFullMap();
        await nextTick();

        expect(wrapper.findComponent({ name: 'MapStub' }).props('popupClearance')).toBe(361);
        rect.mockRestore();
    });

    // The mobile page still remounts its map when the full-map toggle
    // resizes it. That remount must pick up the pins the store holds now,
    // not the ones the page was served with.
    it('remounts the map with the current pins, not the initial ones', async () => {
        const wrapper = mountPage(LocationMobile, pinsFor([1, 2, 3]));
        const before = wrapper.findComponent({ name: 'MapStub' });
        const fresh = pinsFor([7, 8]);
        SearchStore.updateState({ pins: fresh });
        await nextTick();

        wrapper.vm.showFullMap();
        await nextTick();

        const opened = wrapper.findComponent({ name: 'MapStub' });
        expect(opened.vm).not.toBe(before.vm);
        expect(toRaw(opened.props('pins'))).toBe(fresh);

        const fresher = pinsFor([11]);
        SearchStore.updateState({ pins: fresher });
        await nextTick();
        wrapper.vm.hideFullMap();
        await nextTick();

        const closed = wrapper.findComponent({ name: 'MapStub' });
        expect(closed.vm).not.toBe(opened.vm);
        expect(toRaw(closed.props('pins'))).toBe(fresher);
    });
});
