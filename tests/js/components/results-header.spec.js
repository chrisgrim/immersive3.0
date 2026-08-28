/**
 * Specs for PageComponents/Search/Components/results-header.vue
 *
 * This replaced a "Results filtered by: <pills>" row that never showed how
 * many results there were — so verifying "did I see every event in this
 * category?" meant counting cards across pages by hand. The count was
 * already in every search response; the page just dropped it.
 *
 * Covers:
 *  - the headline: count + the ONE thing that answers "why these events?"
 *    (map area > city > At Home type), with map area outranking a stale city.
 *  - the sub-line names the EXTRA filter dimensions only, never repeating
 *    what the headline already said, and calls the store's `tags` "genres".
 *  - zero results render nothing, leaving each page's own empty state alone.
 */
import { mount } from '@vue/test-utils';
import ResultsHeader from '@/PageComponents/Search/Components/results-header.vue';
import SearchStore from '@/Stores/SearchStore.vue';

// The component resolves an At Home slug on mount; no spec here exercises
// that path, so keep it from reaching the network.
vi.mock('axios', () => ({
    default: { get: vi.fn(() => Promise.resolve({ data: [] })) },
}));

function setStore({ filters = {}, location = {} } = {}) {
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
    SearchStore.state.location = { city: null, lat: null, lng: null, live: false, ...location };
}

function headline(props = {}) {
    return mount(ResultsHeader, { props: { total: 41, ...props } });
}

describe('results-header.vue', () => {
    beforeEach(() => setStore());

    describe('the headline', () => {
        it('names the city the search is in', () => {
            setStore({ location: { city: 'New York' } });
            expect(headline().find('h1').text()).toBe('41 Events in New York');
        });

        it('says "in the map area" once the map has been dragged', () => {
            setStore({ location: { live: true } });
            expect(headline().find('h1').text()).toBe('41 Events in the map area');
        });

        it('prefers the map area over the city the search started from', () => {
            // Once you move the map the results ARE the viewport — saying
            // "in New York" while showing New Jersey is the wrong answer.
            setStore({ location: { city: 'New York', live: true } });
            expect(headline().find('h1').text()).toBe('41 Events in the map area');
        });

        it('names the At Home type when one is picked', () => {
            setStore({ filters: { atHome: true, remoteLocation: { slug: 'telephone', name: 'Telephone' } } });
            expect(headline().find('h1').text()).toBe('41 Events in Telephone');
        });

        it('falls back to "at home" rather than printing an unresolved slug', () => {
            // remoteLocation is a raw slug string until it resolves; the old
            // component printed a generic "Remote Events" label, which read as
            // "Events in Remote Events" once it moved into a sentence.
            setStore({ filters: { atHome: true, remoteLocation: 'telephone' } });
            expect(headline().find('h1').text()).toBe('41 Events at home');
        });

        it('drops the context entirely for an unscoped search', () => {
            expect(headline().find('h1').text()).toBe('41 Events');
        });

        it('singularises a lone result', () => {
            setStore({ location: { city: 'Chicago' } });
            expect(headline({ total: 1 }).find('h1').text()).toBe('1 Event in Chicago');
        });

        it('groups the digits of a large count', () => {
            expect(headline({ total: 1234 }).find('h1').text()).toBe('1,234 Events');
        });
    });

    describe('the filter sub-line', () => {
        it('is absent when nothing beyond the headline is filtering', () => {
            setStore({ location: { city: 'New York' } });
            expect(headline().find('button').exists()).toBe(false);
        });

        it('names the dimension, not the values', () => {
            setStore({ filters: { categories: [5] } });
            expect(headline().find('button').text()).toBe('Search filtered by category');
        });

        it('calls the store\'s "tags" genres, the name users see everywhere else', () => {
            setStore({ filters: { tags: [1, 2] } });
            expect(headline().find('button').text()).toBe('Search filtered by genres');
        });

        it('lists several dimensions in a readable sentence', () => {
            setStore({ filters: { categories: [5], tags: [1], price: [20, 80], maxPrice: 200 } });
            expect(headline().find('button').text()).toBe('Search filtered by category, genre and price');
        });

        it('ignores a price range that covers everything', () => {
            // The slider sits at [0, maxPrice] by default — that is not a
            // filter, and reporting it would make the line permanent noise.
            setStore({ filters: { price: [0, 200], maxPrice: 200 } });
            expect(headline().find('button').exists()).toBe(false);
        });

        it('does not repeat the At Home type the headline already named', () => {
            setStore({ filters: { atHome: true, remoteLocation: { slug: 'telephone', name: 'Telephone' } } });
            const wrapper = headline();
            expect(wrapper.find('h1').text()).toBe('41 Events in Telephone');
            expect(wrapper.find('button').exists()).toBe(false);
        });

        it('opens the filter modal, preserving the old pills\' one affordance', async () => {
            setStore({ filters: { categories: [5] } });
            const listener = vi.fn();
            window.addEventListener('open-filters', listener);

            await headline().find('button').trigger('click');

            expect(listener).toHaveBeenCalled();
            window.removeEventListener('open-filters', listener);
        });
    });

    it('renders nothing at all when there are no results', () => {
        // Both pages have their own empty state; a "0 Events" headline above
        // "No events match your filters" would just say it twice.
        setStore({ location: { city: 'New York' } });
        expect(headline({ total: 0 }).find('h1').exists()).toBe(false);
    });
});
