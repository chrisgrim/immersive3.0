/**
 * Specs for PageComponents/Profile/Components/saved-search-card.vue
 *
 * Covers:
 *  - emits remove-requested / pin-toggled / select with the search payload.
 *  - the pin icon/label reflects search.pinned.
 *  - the `summary` computed: city, searchType, remoteLocation title-casing,
 *    custom map area, categories, and genres — specifically that the
 *    backend's `tags` criteria key is always rendered as "genre(s)", never
 *    "tag(s)" (a labeling fix from earlier this session).
 */
import { mount } from '@vue/test-utils';
import SavedSearchCard from '@/PageComponents/Profile/Components/saved-search-card.vue';

function makeSearch(overrides = {}) {
    return {
        id: 1,
        name: 'My Search',
        url: '/index/search?city=New+York',
        pinned: false,
        criteria: {},
        ...overrides,
    };
}

function mountCard(props = {}) {
    return mount(SavedSearchCard, {
        props: { search: makeSearch(), ...props },
    });
}

describe('saved-search-card.vue', () => {
    it('emits remove-requested with the search on the remove button click', async () => {
        const search = makeSearch();
        const wrapper = mountCard({ search });
        await wrapper.find('button[aria-label="Remove saved search"]').trigger('click');
        expect(wrapper.emitted('remove-requested')[0]).toEqual([search]);
    });

    it('emits select with the search when the card body is clicked', async () => {
        const search = makeSearch();
        const wrapper = mountCard({ search });
        await wrapper.findAll('button')[1].trigger('click'); // second button is the select body
        expect(wrapper.emitted('select')[0]).toEqual([search]);
    });

    it('emits pin-toggled with the search when the pin button is clicked', async () => {
        const search = makeSearch();
        const wrapper = mountCard({ search });
        await wrapper.find('button[aria-label="Pin search"]').trigger('click');
        expect(wrapper.emitted('pin-toggled')[0]).toEqual([search]);
    });

    it('shows "Unpin search" label when pinned, "Pin search" when not', () => {
        const pinned = mountCard({ search: makeSearch({ pinned: true }) });
        const unpinned = mountCard({ search: makeSearch({ pinned: false }) });
        expect(pinned.find('button[aria-label="Unpin search"]').exists()).toBe(true);
        expect(unpinned.find('button[aria-label="Pin search"]').exists()).toBe(true);
    });

    it('applies the selected ring class when selected is true', () => {
        const wrapper = mountCard({ selected: true });
        expect(wrapper.find('.group').classes()).toContain('ring-2');
    });

    describe('summary text', () => {
        it('shows "All events" when criteria is empty', () => {
            const wrapper = mountCard({ search: makeSearch({ criteria: {} }) });
            expect(wrapper.text()).toContain('All events');
        });

        it('includes city and In-person for an in-person search', () => {
            const wrapper = mountCard({
                search: makeSearch({ criteria: { city: 'Chicago, IL', searchType: 'inPerson' } }),
            });
            expect(wrapper.text()).toContain('Chicago, IL · In-person');
        });

        it('title-cases the remoteLocation slug for an At Home search', () => {
            const wrapper = mountCard({
                search: makeSearch({ criteria: { searchType: 'atHome', remoteLocation: 'web-site' } }),
            });
            expect(wrapper.text()).toContain('Web Site');
        });

        it('falls back to "Remote" for an At Home search with no remoteLocation', () => {
            const wrapper = mountCard({ search: makeSearch({ criteria: { searchType: 'atHome' } }) });
            expect(wrapper.text()).toContain('Remote');
        });

        it('includes "Custom map area" when criteria.live is true', () => {
            const wrapper = mountCard({ search: makeSearch({ criteria: { live: true } }) });
            expect(wrapper.text()).toContain('Custom map area');
        });

        it('pluralizes categories correctly', () => {
            const one = mountCard({ search: makeSearch({ criteria: { categories: [1] } }) });
            const many = mountCard({ search: makeSearch({ criteria: { categories: [1, 2, 3] } }) });
            expect(one.text()).toContain('1 category');
            expect(many.text()).toContain('3 categories');
        });

        it('labels the backend "tags" criteria key as genre(s), never tag(s)', () => {
            const one = mountCard({ search: makeSearch({ criteria: { tags: [1] } }) });
            const many = mountCard({ search: makeSearch({ criteria: { tags: [1, 2] } }) });

            expect(one.text()).toContain('1 genre');
            expect(one.text()).not.toContain('tag');
            expect(many.text()).toContain('2 genres');
            expect(many.text()).not.toContain('tag');
        });

        it('joins multiple criteria parts with " · "', () => {
            const wrapper = mountCard({
                search: makeSearch({ criteria: { city: 'NYC', categories: [1], tags: [1, 2] } }),
            });
            expect(wrapper.text()).toContain('NYC · 1 category · 2 genres');
        });
    });
});
