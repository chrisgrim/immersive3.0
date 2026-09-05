/**
 * Specs for PageComponents/Nav/Components/location-search.vue
 *
 * The desktop search bar. Covered here: hydrating the date range on a cold
 * load. nav-search.vue fills SearchStore — and so this component's
 * initial-*-date props — in its own onMounted, which runs AFTER this
 * child's, so the bar has to read start/end from the URL itself. Before it
 * did, a replayed saved search with dates landed with a "Dates" pill over a
 * date-filtered list, and clicking Search dropped the range from the URL.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { vi } from 'vitest';
import LocationSearch from '@/PageComponents/Nav/Components/location-search.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(() => Promise.resolve({ data: [] })), post: vi.fn(() => Promise.resolve({ data: {} })) } }));
vi.mock('@/composables/useGoogleMaps', () => ({ importMapsLibrary: vi.fn(() => Promise.resolve({ AutocompleteService: class {} })) }));
vi.mock('@/composables/useSavedSearches', () => ({ saveSearch: vi.fn(() => Promise.resolve()) }));
vi.mock('@/composables/useRecentSearches', async () => {
    const { ref } = await import('vue');
    return { useRecentSearches: () => ({ recentSearches: ref([]), fetchRecentSearches: vi.fn() }) };
});

const CITY = 'city=New+York%2C+NY&lat=40.7127753&lng=-74.0059728&searchType=inPerson&live=false';
const DATES = 'start=2026-10-03+00%3A00%3A00&end=2026-10-23+00%3A00%3A00';

let navigatedTo;

function stubLocation(search) {
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

// onMounted sets the date ref, so the pill re-renders one tick after mount.
async function mountBar(search, props = {}) {
    stubLocation(search);
    const wrapper = mount(LocationSearch, {
        props,
        global: {
            stubs: { VueDatePicker: true, Filters: true, SearchFilterButton: true, RecentSearchesList: true },
            directives: { 'click-outside': { beforeMount() {}, mounted() {}, unmounted() {} } },
        },
    });
    await flushPromises();
    return wrapper;
}

const pill = (wrapper) => wrapper.find('.location-search-date-btn').text();

describe('location-search.vue', () => {
    describe('the date range on a cold load', () => {
        it('shows the URL dates before the parent has hydrated the store', async () => {
            const wrapper = await mountBar(`?${CITY}&${DATES}`, { compact: true });

            expect(pill(wrapper)).toBe('Oct 3 - Oct 23');
        });

        it('keeps the range when Search is clicked without touching the dates', async () => {
            // On the results page the bar emits the search for nav-search to
            // run rather than navigating; the range must be in that payload.
            const wrapper = await mountBar(`?${CITY}&${DATES}`);
            await wrapper.vm.handleSearch();
            await flushPromises();

            const [payload] = wrapper.emitted('search').at(-1);
            expect(payload.location.city).toBe('New York, NY');
            expect(payload.dates).toEqual({ start: '2026-10-03 00:00:00', end: '2026-10-23 00:00:00' });
        });

        it('falls back to the initial-*-date props when the URL has none', async () => {
            const wrapper = await mountBar(`?${CITY}`, {
                initialStartDate: '2026-10-03 00:00:00', initialEndDate: '2026-10-23 00:00:00',
            });

            expect(pill(wrapper)).toBe('Oct 3 - Oct 23');
        });

        it('says Dates when neither has any', async () => {
            expect(pill(await mountBar(`?${CITY}`))).toBe('Dates');
        });
    });
});
