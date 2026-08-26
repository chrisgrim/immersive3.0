/**
 * Specs for PageComponents/Nav/Components/at-home-search-mobile.vue
 *
 * This component shipped with 465 lines and no tests, in the global mobile
 * nav — every page on the site. What's covered here is the logic that has
 * no other guard: the synthetic "All At Home" option, the shared dropdown
 * budget, the stale-response race guard, and the auto-save fired on search.
 *
 * NOTE ON MOCKING: this component (and the useSavedSearches composable it
 * calls) do `import axios from 'axios'` rather than reading window.axios, so
 * the global window.axios mock in tests/js/setup.js does NOT intercept them.
 * Both are mocked as modules below.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { vi } from 'vitest';
import axios from 'axios';
import AtHomeSearchMobile from '@/PageComponents/Nav/Components/at-home-search-mobile.vue';
import { saveSearch } from '@/composables/useSavedSearches';

vi.mock('axios', () => ({
    default: { get: vi.fn(), post: vi.fn() },
}));

vi.mock('@/composables/useSavedSearches', () => ({
    saveSearch: vi.fn(() => Promise.resolve()),
}));

const TYPES = [
    { id: 1, name: 'Zoom', slug: 'zoom' },
    { id: 2, name: 'Discord', slug: 'discord' },
    { id: 3, name: 'Phone', slug: 'phone' },
    { id: 4, name: 'Mail', slug: 'mail' },
    { id: 5, name: 'VR', slug: 'vr' },
    { id: 6, name: 'Twitch', slug: 'twitch' },
    { id: 7, name: 'YouTube', slug: 'youtube' },
];

// The component fires two different GETs on mount (types + saved searches);
// route by URL so ordering between them doesn't matter.
function routeGet({ types = TYPES, searches = [] } = {}) {
    axios.get.mockImplementation((url) => {
        if (url === '/api/hub/saved-searches') return Promise.resolve({ data: { searches } });
        return Promise.resolve({ data: types });
    });
}

function mountSearch(props = {}) {
    return mount(AtHomeSearchMobile, {
        props,
        global: {
            stubs: { VueDatePicker: true, RecentSearchesList: true },
        },
    });
}

beforeEach(() => {
    axios.get.mockReset();
    saveSearch.mockClear();
    window.Laravel = { user: { id: 1 } };
});

describe('at-home-search-mobile.vue', () => {
    describe('the synthetic "All At Home" option', () => {
        it('is prepended to the default type list', async () => {
            routeGet();
            const wrapper = mountSearch();
            await flushPromises();

            const items = wrapper.findAll('ul li').map((li) => li.text());
            expect(items[items.findIndex((t) => t.includes('All At Home'))]).toContain('All At Home');
        });

        it('searches with no platform filter when picked, since its slug is null', async () => {
            routeGet();
            const wrapper = mountSearch();
            await flushPromises();

            const all = wrapper.findAll('ul li').find((li) => li.text().includes('All At Home'));
            await all.trigger('click');

            await wrapper.findAll('button').find((b) => b.text() === 'Search').trigger('click');
            await flushPromises();

            expect(wrapper.emitted('search')[0][0].remoteLocation).toBeNull();
        });

        it('survives as the only option when the types request fails', async () => {
            axios.get.mockImplementation((url) => {
                if (url === '/api/hub/saved-searches') return Promise.resolve({ data: { searches: [] } });
                return Promise.reject(new Error('network'));
            });
            vi.spyOn(console, 'error').mockImplementation(() => {});
            const wrapper = mountSearch();
            await flushPromises();

            const items = wrapper.findAll('ul li').map((li) => li.text());
            expect(items.some((t) => t.includes('All At Home'))).toBe(true);
        });
    });

    describe('shared dropdown budget', () => {
        it('gives up type slots one-for-one to recent searches', async () => {
            // DROPDOWN_TOTAL_LIMIT is 6: 2 recents must leave room for
            // "All At Home" + 3 fetched types, not all 7 fetched types.
            routeGet({ searches: [{ id: 1, name: 'a' }, { id: 2, name: 'b' }] });
            const wrapper = mountSearch();
            await flushPromises();

            const named = wrapper.findAll('ul li').map((li) => li.text());
            const fetched = TYPES.filter((t) => named.some((n) => n.includes(t.name)));
            expect(fetched).toHaveLength(4);
        });

        it('uses the full budget when there are no recent searches', async () => {
            routeGet({ searches: [] });
            const wrapper = mountSearch();
            await flushPromises();

            const named = wrapper.findAll('ul li').map((li) => li.text());
            const fetched = TYPES.filter((t) => named.some((n) => n.includes(t.name)));
            expect(fetched).toHaveLength(6);
        });
    });

    describe('stale-response race guard', () => {
        it('ignores a slow earlier response that resolves after a newer one', async () => {
            // The real failure: type "z", then keep typing to "zo". If the
            // "z" request resolves last, an unguarded component renders "z"
            // results under a "zo" query.
            routeGet();
            const wrapper = mountSearch();
            await flushPromises();

            let resolveSlow;
            const slow = new Promise((r) => { resolveSlow = r; });
            axios.get.mockImplementationOnce(() => slow);
            const input = wrapper.find('input[type="text"]');
            await input.setValue('z');

            axios.get.mockImplementationOnce(() => Promise.resolve({ data: [{ id: 9, name: 'Zoom Fresh', slug: 'zf' }] }));
            await input.setValue('zo');
            await flushPromises();

            // Now let the stale one land.
            resolveSlow({ data: [{ id: 8, name: 'Stale Result', slug: 'stale' }] });
            await flushPromises();

            const text = wrapper.find('ul').text();
            expect(text).toContain('Zoom Fresh');
            expect(text).not.toContain('Stale Result');
        });
    });

    describe('search', () => {
        it('auto-saves the search criteria before emitting', async () => {
            routeGet();
            const wrapper = mountSearch();
            await flushPromises();

            const zoom = wrapper.findAll('ul li').find((li) => li.text().includes('Zoom'));
            await zoom.trigger('click');
            await wrapper.findAll('button').find((b) => b.text() === 'Search').trigger('click');
            await flushPromises();

            expect(saveSearch).toHaveBeenCalledWith('Zoom', expect.objectContaining({
                searchType: 'atHome',
                remoteLocation: 'zoom',
            }));
        });

        it('does nothing when neither a type nor dates are chosen', async () => {
            routeGet();
            const wrapper = mountSearch();
            await flushPromises();

            await wrapper.findAll('button').find((b) => b.text() === 'Search').trigger('click');
            await flushPromises();

            expect(saveSearch).not.toHaveBeenCalled();
            expect(wrapper.emitted('search')).toBeUndefined();
        });
    });

    describe('recent searches visibility', () => {
        it('hides them once the user starts typing a query', async () => {
            routeGet({ searches: [{ id: 1, name: 'a' }] });
            const wrapper = mountSearch();
            await flushPromises();
            expect(wrapper.text()).toContain('Recent searches');

            await wrapper.find('input[type="text"]').setValue('zo');
            await flushPromises();
            expect(wrapper.text()).not.toContain('Recent searches');
        });
    });
});
