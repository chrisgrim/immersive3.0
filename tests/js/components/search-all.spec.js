/**
 * Specs for PageComponents/Search/all.vue — the no-map results list (at
 * home / bare searches) — covering its half of the "Show more" change. The
 * map page's behaviour lives in search-location.spec.js; this guards that
 * the list page asks for the same window, replaces its grid with it, and
 * records the depth the same way, since it used to carry its own copy of
 * the page-change code and drifted.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { vi } from 'vitest';

vi.mock('axios', () => ({ default: { get: vi.fn(() => Promise.resolve({ data: {} })) } }));
vi.mock('@/GlobalComponents/Grid/event-grid.vue', () => ({
    default: { name: 'GridStub', props: ['items', 'columns', 'showLocation'], template: '<div class="grid-stub" />' },
}));
vi.mock('@/PageComponents/Search/Components/results-header.vue', () => ({
    default: { name: 'ResultsHeaderStub', props: ['total', 'initialRemoteLocation'], template: '<div />' },
}));
vi.mock('@/PageComponents/Search/Components/show-more-results.vue', () => ({
    default: { name: 'ShowMoreResults', props: ['shown', 'total', 'hasMore', 'limitReached', 'loading'], template: '<div />' },
}));

import axios from 'axios';
import SearchAll from '@/PageComponents/Search/all.vue';
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

// The nav seeds the store from the same server payload in the real app, so
// the page's own subscription (which fires immediately) sees the same list
// it was rendered with rather than an empty store.
function mountAll(searchedEvents = page([1, 2])) {
    SearchStore.state.events = { ...searchedEvents };
    return mount(SearchAll, { props: { searchedEvents } });
}

const searchCall = () => axios.get.mock.calls.find(([url]) => url.includes('/api/index/search'));

beforeEach(() => {
    SearchStore.state.events = { data: [], total: 0, current_page: 1, per_page: 20, from: null, to: null, last_page: 1, has_more: false };
    SearchStore.state.filters = { categories: [], tags: [], price: [0, null], maxPrice: null, searchingByPrice: false, atHome: true, remoteLocation: null };
    SearchStore.state.location = { city: null, lat: null, lng: null, live: false };
    SearchStore.listeners.clear();
    axios.get.mockReset();
    axios.get.mockResolvedValue({ data: {} });
    window.history.pushState = vi.fn();
    window.history.replaceState = vi.fn();
});

describe('all.vue — Show more', () => {
    it('offers Show more with the count, following the server', () => {
        const wrapper = mountAll();

        const more = wrapper.findComponent({ name: 'ShowMoreResults' });
        expect(more.props('shown')).toBe(2);
        expect(more.props('total')).toBe(40);
        expect(more.props('hasMore')).toBe(true);
    });

    it('asks for the whole window, replaces the grid with it, and records the depth', async () => {
        const wrapper = mountAll();
        axios.get.mockResolvedValue({
            data: page([1, 2, 3, 4], { current_page: 2, to: 4, has_more: false }),
        });

        await wrapper.vm.handleShowMore();
        await flushPromises();

        const asked = new URLSearchParams(searchCall()[0].split('?')[1]);
        expect(asked.get('pages')).toBe('2');
        expect(asked.has('page')).toBe(false);
        expect(asked.get('include_pins')).toBe('0');

        expect(wrapper.findComponent({ name: 'GridStub' }).props('items')).toEqual([{ id: 1 }, { id: 2 }, { id: 3 }, { id: 4 }]);
        expect(wrapper.findComponent({ name: 'ShowMoreResults' }).props('hasMore')).toBe(false);

        expect(window.history.pushState).not.toHaveBeenCalled();
        const url = new URLSearchParams(window.history.replaceState.mock.calls[0][2].split('?')[1]);
        expect(url.get('page')).toBe('2');
        expect(url.has('pages')).toBe(false);
    });

    it('no longer re-fetches on mount when the URL carries a page — the server rendered that depth', async () => {
        delete window.location;
        window.location = { pathname: '/index/search', search: '?searchType=atHome&page=3' };

        mountAll(page([1, 2, 3, 4, 5, 6], { current_page: 3 }));
        await flushPromises();

        expect(searchCall()).toBeUndefined();
    });
});
