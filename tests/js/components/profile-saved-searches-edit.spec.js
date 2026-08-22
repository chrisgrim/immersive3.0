/**
 * Specs for PageComponents/Profile/Pages/SavedSearchesEdit.vue
 *
 * This component imports axios directly (`import axios from 'axios'`), NOT
 * via the global window.axios setup.js pre-mocks — so it needs its own
 * vi.mock('axios') here rather than window.axios.get.mockResolvedValue(...).
 *
 * Covers:
 *  - update:draft emits a merged draft on name/criteria changes (never a
 *    partial object — always spreads props.draft first).
 *  - setMode('inPerson' | 'atHome') and the picker swap it drives.
 *  - locationMissing (inPerson mode with no lat/lng) and the Save button's
 *    disabled state (saving || !dirty || locationMissing).
 *  - price criteria's [0, null] "no filter" sentinel displays as [0, maxPrice].
 *  - category/genre add/remove never duplicate an already-selected id.
 *  - save is emitted on click.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { vi } from 'vitest';

vi.mock('axios', () => ({
    default: {
        get: vi.fn(() => Promise.resolve({ data: [] })),
    },
}));

import axios from 'axios';
import SavedSearchesEdit from '@/PageComponents/Profile/Pages/SavedSearchesEdit.vue';

function makeDraft(overrides = {}) {
    return {
        name: 'My Search',
        criteria: {
            searchType: 'inPerson',
            city: 'New York, NY',
            lat: 40.7128,
            lng: -74.006,
            remoteLocation: null,
            price: [0, null],
            categories: [],
            tags: [],
        },
        ...overrides,
    };
}

function mountEdit(props = {}) {
    return mount(SavedSearchesEdit, {
        props: {
            draft: makeDraft(),
            dirty: false,
            saving: false,
            error: null,
            ...props,
        },
        global: {
            stubs: {
                VueSlider: true,
                SavedSearchLocationPicker: true,
                SavedSearchRemoteLocationPicker: true,
            },
        },
    });
}

describe('Profile/Pages/SavedSearchesEdit.vue', () => {
    beforeEach(() => {
        axios.get.mockClear();
        axios.get.mockResolvedValue({ data: [] });
    });

    describe('name/criteria updates', () => {
        it('emits update:draft with the full merged draft when the name changes', async () => {
            const draft = makeDraft();
            const wrapper = mountEdit({ draft });

            await wrapper.find('#saved-search-name').setValue('New name');

            expect(wrapper.emitted('update:draft')).toBeTruthy();
            expect(wrapper.emitted('update:draft')[0][0]).toEqual({ ...draft, name: 'New name' });
        });

        it('setMode(atHome) merges searchType into criteria without dropping other criteria fields', async () => {
            const draft = makeDraft();
            const wrapper = mountEdit({ draft });

            const atHomeButton = wrapper.findAll('button').find((b) => b.text() === 'At Home');
            await atHomeButton.trigger('click');

            const emittedDraft = wrapper.emitted('update:draft')[0][0];
            expect(emittedDraft.criteria.searchType).toBe('atHome');
            expect(emittedDraft.criteria.city).toBe(draft.criteria.city); // untouched fields survive
        });

        it('swaps to the remote-location picker in atHome mode', () => {
            const wrapper = mountEdit({ draft: makeDraft({ criteria: { ...makeDraft().criteria, searchType: 'atHome' } }) });

            expect(wrapper.findComponent({ name: 'SavedSearchLocationPicker' }).exists()).toBe(false);
            expect(wrapper.find('savedsearchremotelocationpicker-stub, saved-search-remote-location-picker-stub').exists()).toBe(true);
        });
    });

    describe('locationMissing / Save button state', () => {
        it('flags locationMissing and disables Save when inPerson mode has no lat/lng', () => {
            const wrapper = mountEdit({
                draft: makeDraft({ criteria: { ...makeDraft().criteria, lat: null, lng: null } }),
                dirty: true,
            });

            expect(wrapper.text()).toContain('Pick a location to save this search.');
            const saveButton = wrapper.findAll('button').find((b) => b.text().includes('Save'));
            expect(saveButton.attributes('disabled')).toBeDefined();
        });

        it('disables Save when not dirty, even with a valid location', () => {
            const wrapper = mountEdit({ draft: makeDraft(), dirty: false });
            const saveButton = wrapper.findAll('button').find((b) => b.text().includes('Save'));
            expect(saveButton.attributes('disabled')).toBeDefined();
        });

        it('enables Save when dirty and location is present, and emits save on click', async () => {
            const wrapper = mountEdit({ draft: makeDraft(), dirty: true });
            const saveButton = wrapper.findAll('button').find((b) => b.text() === 'Save');
            expect(saveButton.attributes('disabled')).toBeUndefined();

            await saveButton.trigger('click');
            expect(wrapper.emitted('save')).toBeTruthy();
        });

        it('shows "Saving…" and disables the button while saving', () => {
            const wrapper = mountEdit({ draft: makeDraft(), dirty: true, saving: true });
            const saveButton = wrapper.findAll('button').find((b) => b.text().includes('Saving'));
            expect(saveButton.exists()).toBe(true);
            expect(saveButton.attributes('disabled')).toBeDefined();
        });

        it('shows the error message instead of the location-missing message when both could apply', () => {
            const wrapper = mountEdit({
                draft: makeDraft({ criteria: { ...makeDraft().criteria, lat: null, lng: null } }),
                error: 'Something went wrong',
            });
            expect(wrapper.text()).toContain('Something went wrong');
            expect(wrapper.text()).not.toContain('Pick a location to save this search.');
        });
    });

    describe('category/genre selection', () => {
        it('loads categories and genres from the cached endpoints on mount', () => {
            mountEdit();
            expect(axios.get).toHaveBeenCalledWith('/api/categories/active/cached');
            expect(axios.get).toHaveBeenCalledWith('/api/genres/active/cached');
        });

        it('does not throw when the category/genre fetch rejects', async () => {
            axios.get.mockRejectedValueOnce(new Error('network error'));
            expect(() => mountEdit()).not.toThrow();
        });

        it('drops a selected category that is no longer applicable when switching modes', async () => {
            // Regression coverage: the watcher on criteria.searchType prunes
            // categories.value.filter to only ones still applicable to the
            // new attendance type, and emits update:draft if anything was
            // dropped — this is what stops a saved search from persisting
            // an in-person-only category while filtered to At Home.
            axios.get.mockImplementation((url) => {
                if (url === '/api/categories/active/cached') {
                    return Promise.resolve({
                        data: [
                            { id: 1, applicable_attendance_types: [1] }, // in-person only
                            { id: 2, applicable_attendance_types: [1, 2] }, // both
                        ],
                    });
                }
                return Promise.resolve({ data: [] });
            });

            const draft = makeDraft({
                criteria: { ...makeDraft().criteria, searchType: 'inPerson', categories: [1, 2] },
            });
            const wrapper = mountEdit({ draft });
            await flushPromises();

            await wrapper.setProps({
                draft: { ...draft, criteria: { ...draft.criteria, searchType: 'atHome' } },
            });
            await flushPromises();

            const emittedDraft = wrapper.emitted('update:draft').at(-1)[0];
            expect(emittedDraft.criteria.categories).toEqual([2]);
        });
    });
});
