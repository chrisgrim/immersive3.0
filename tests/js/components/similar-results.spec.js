/**
 * Specs for PageComponents/Search/Components/similar-results.vue
 *
 * The empty state said "Sorry, we couldn't find any events in Los Angeles /
 * Here are some events in nearby areas you might be interested in:" — which
 * never mentioned that the search had filters on it, and that the filters
 * were almost certainly the reason nothing matched.
 *
 * Also covers the blank-page bug this sat on: the whole block, heading
 * included, used to render only when there were nearby events to suggest, so
 * a search with no matches AND nothing nearby explained nothing at all.
 */
import { mount, flushPromises } from '@vue/test-utils';
import SimilarResults from '@/PageComponents/Search/Components/similar-results.vue';
import SearchStore from '@/Stores/SearchStore.vue';

// tests/js/setup.js installs a fully-mocked window.axios and rebuilds it
// before every test, so drive it through that rather than stubbing over it.
function setFilters(filters = {}) {
    SearchStore.state.filters = {
        categories: [],
        tags: [],
        price: [0, null],
        maxPrice: null,
        searchingByPrice: false,
        atHome: false,
        remoteLocation: null,
        ...filters,
    };
}

function nearby(count) {
    return Array.from({ length: count }, (_, i) => ({
        id: i + 1,
        slug: `nearby-${i + 1}`,
        name: `Nearby Event ${i + 1}`,
        images: [],
    }));
}

async function mountEmptyState({ suggestions = 1, isRemote = false } = {}) {
    window.axios.get.mockResolvedValue({ data: { events: nearby(suggestions), isRemote } });

    const wrapper = mount(SimilarResults, {
        props: { columns: 4 },
        global: { stubs: { EventList: { template: '<div class="event-list" />' } } },
    });

    await flushPromises();
    return wrapper;
}

describe('similar-results.vue', () => {
    beforeEach(() => {
        setFilters();
        window.history.replaceState({}, '', '/index/search?city=Los+Angeles,+CA&lat=34&lng=-118');
    });

    it('names the city that came back empty', async () => {
        const wrapper = await mountEmptyState();
        expect(wrapper.text()).toContain("Sorry, we couldn't find any events in Los Angeles");
    });

    describe('when the filter panel is narrowing the search', () => {
        it('leads with the filters being the reason', async () => {
            setFilters({ categories: [5] });
            const wrapper = await mountEmptyState({ suggestions: 3 });

            expect(wrapper.text()).toContain('Please remove filters to see more events');
        });

        it('opens the filter panel, so "remove filters" is one click not a hunt', async () => {
            setFilters({ categories: [5] });
            const listener = vi.fn();
            window.addEventListener('open-filters', listener);

            const wrapper = await mountEmptyState();
            await wrapper.get('button').trigger('click');

            expect(listener).toHaveBeenCalled();
            window.removeEventListener('open-filters', listener);
        });

        it('still fills the column with suggestions rather than leaving it blank', async () => {
            setFilters({ tags: [1] });
            const wrapper = await mountEmptyState({ suggestions: 3 });

            expect(wrapper.find('.event-list').exists()).toBe(true);
        });

        it('marks the suggestions as OTHER events, below a clear gap', async () => {
            // They are fetched on lat/lng alone, ignoring the filters, so
            // directly under "remove your filters" they would read as an
            // answer to it. The wording and the gap both say otherwise.
            setFilters({ tags: [1] });
            const wrapper = await mountEmptyState({ suggestions: 3 });

            expect(wrapper.text()).toContain('Here are some other events in nearby areas');
            expect(wrapper.html()).toContain('mt-12');
        });

        it('counts a real price range but not the default full-range slider', async () => {
            setFilters({ price: [0, 200], maxPrice: 200 });
            expect((await mountEmptyState()).text()).not.toContain('Please remove filters');

            setFilters({ price: [20, 80], maxPrice: 200 });
            expect((await mountEmptyState()).text()).toContain('Please remove filters');
        });
    });

    describe('when nothing is filtered', () => {
        it('offers the nearby events as before, with no "other" and no gap', async () => {
            const wrapper = await mountEmptyState({ suggestions: 3 });

            expect(wrapper.text()).toContain('Here are some events in nearby areas');
            expect(wrapper.text()).not.toContain('some other events');
            expect(wrapper.find('.event-list').exists()).toBe(true);
        });

        it('leaves the nearby-areas line as plain text, with nothing to click', async () => {
            const wrapper = await mountEmptyState({ suggestions: 3 });
            expect(wrapper.find('button').exists()).toBe(false);
        });

        it('offers online events when the suggestions are remote', async () => {
            const wrapper = await mountEmptyState({ suggestions: 2, isRemote: true });
            expect(wrapper.text()).toContain('Here are some online events');
        });

        it('still explains itself when there is nothing nearby to suggest', async () => {
            // The blank-page case: no matches, no suggestions, and previously
            // no message either. The intro must not appear over an empty grid.
            const wrapper = await mountEmptyState({ suggestions: 0 });

            expect(wrapper.text()).toContain("Sorry, we couldn't find any events in Los Angeles");
            expect(wrapper.text()).not.toContain('nearby areas');
            expect(wrapper.find('.event-list').exists()).toBe(false);
        });
    });
});
