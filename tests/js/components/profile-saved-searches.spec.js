/**
 * Specs for PageComponents/Profile/Pages/SavedSearches.vue
 *
 * Covers:
 *  - Loading / empty / list states.
 *  - `selected` prop is only true for the card matching selectedSearch.id.
 *  - select/pin-toggled/remove-requested are re-emitted verbatim from
 *    saved-search-card (this component owns no fetching/undo logic itself —
 *    that lives in the parent shell per its own comment).
 */
import { mount } from '@vue/test-utils';
import SavedSearches from '@/PageComponents/Profile/Pages/SavedSearches.vue';
import SavedSearchCard from '@/PageComponents/Profile/Components/saved-search-card.vue';

function makeSearch(overrides = {}) {
    return {
        id: 1,
        name: 'Broadway NYC',
        criteria: { city: 'New York, NY' },
        pinned: false,
        url: '/index/search?city=New+York%2C+NY',
        ...overrides,
    };
}

function mountSavedSearches(props = {}) {
    return mount(SavedSearches, {
        props: {
            searches: [],
            loading: false,
            selectedSearch: null,
            ...props,
        },
    });
}

describe('Profile/Pages/SavedSearches.vue', () => {
    describe('states', () => {
        it('shows a loading message while loading', () => {
            const wrapper = mountSavedSearches({ loading: true });
            expect(wrapper.text()).toContain('Loading');
        });

        it('shows an empty state when there are no saved searches', () => {
            const wrapper = mountSavedSearches({ searches: [] });
            expect(wrapper.text()).toContain('Nothing saved yet');
        });

        it('renders a card per search', () => {
            const wrapper = mountSavedSearches({ searches: [makeSearch({ id: 1 }), makeSearch({ id: 2 })] });
            expect(wrapper.findAllComponents(SavedSearchCard)).toHaveLength(2);
        });
    });

    describe('selection', () => {
        it('marks only the card matching selectedSearch.id as selected', () => {
            const a = makeSearch({ id: 1 });
            const b = makeSearch({ id: 2 });
            const wrapper = mountSavedSearches({ searches: [a, b], selectedSearch: b });

            const cards = wrapper.findAllComponents(SavedSearchCard);
            expect(cards[0].props('selected')).toBe(false);
            expect(cards[1].props('selected')).toBe(true);
        });

        it('marks no card as selected when selectedSearch is null', () => {
            const wrapper = mountSavedSearches({ searches: [makeSearch({ id: 1 })], selectedSearch: null });
            expect(wrapper.findComponent(SavedSearchCard).props('selected')).toBe(false);
        });
    });

    describe('event re-emission', () => {
        it('re-emits select with the card payload', async () => {
            const search = makeSearch();
            const wrapper = mountSavedSearches({ searches: [search] });

            await wrapper.findComponent(SavedSearchCard).vm.$emit('select', search);

            expect(wrapper.emitted('select')).toBeTruthy();
            expect(wrapper.emitted('select')[0]).toEqual([search]);
        });

        it('re-emits pin-toggled with the card payload', async () => {
            const search = makeSearch();
            const wrapper = mountSavedSearches({ searches: [search] });

            await wrapper.findComponent(SavedSearchCard).vm.$emit('pin-toggled', search);

            expect(wrapper.emitted('pin-toggled')).toBeTruthy();
            expect(wrapper.emitted('pin-toggled')[0]).toEqual([search]);
        });

        it('re-emits remove-requested with the card payload', async () => {
            const search = makeSearch();
            const wrapper = mountSavedSearches({ searches: [search] });

            await wrapper.findComponent(SavedSearchCard).vm.$emit('remove-requested', search);

            expect(wrapper.emitted('remove-requested')).toBeTruthy();
            expect(wrapper.emitted('remove-requested')[0]).toEqual([search]);
        });
    });
});
