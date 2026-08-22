/**
 * Specs for Profile/Components/saved-search-remote-location-picker.vue
 *
 * Covers:
 *  - select() (RemoteTypeSearchInput's @select): commits query + emits
 *    update:modelValue with the picked type's slug.
 *  - onMounted slug resolution: with a modelValue, fetches the public
 *    remote-locations list and sets query to the matching name.
 *  - Falls back to a title-cased version of the slug when the API call
 *    fails, or when the slug isn't found in the (still-bookable-only) list.
 *  - No fetch at all when modelValue is null.
 *
 * This component imports axios directly (`import axios from 'axios'`), not
 * the global window.axios other components in this codebase read — so it's
 * mocked here via vi.mock('axios'), not window.axios.get.mockResolvedValue.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import axios from 'axios';
import SavedSearchRemoteLocationPicker from '@/PageComponents/Profile/Components/saved-search-remote-location-picker.vue';
import RemoteTypeSearchInput from '@/PageComponents/Search/Components/remote-type-search-input.vue';

vi.mock('axios', () => ({
    default: { get: vi.fn() },
}));

function makeWrapper(props = {}) {
    return mount(SavedSearchRemoteLocationPicker, {
        props,
        global: {
            stubs: { RemoteTypeSearchInput: true },
        },
    });
}

beforeEach(() => {
    axios.get.mockReset();
});

describe('saved-search-remote-location-picker.vue', () => {
    it('does not fetch when modelValue is null', async () => {
        makeWrapper({ modelValue: null });
        await flushPromises();

        expect(axios.get).not.toHaveBeenCalled();
    });

    it('resolves modelValue to its real display name on mount', async () => {
        axios.get.mockResolvedValue({
            data: [
                { slug: 'zoom', name: 'Zoom' },
                { slug: 'telephone', name: 'Telephone' },
            ],
        });

        const wrapper = makeWrapper({ modelValue: 'telephone' });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/remotelocations/public', { params: { limit: 20 } });
        expect(wrapper.findComponent(RemoteTypeSearchInput).props('modelValue')).toBe('Telephone');
    });

    it('falls back to a title-cased slug when the slug is not in the (bookable-only) list', async () => {
        axios.get.mockResolvedValue({ data: [{ slug: 'zoom', name: 'Zoom' }] });

        const wrapper = makeWrapper({ modelValue: 'my-custom-link' });
        await flushPromises();

        expect(wrapper.findComponent(RemoteTypeSearchInput).props('modelValue')).toBe('My Custom Link');
    });

    it('falls back to a title-cased slug when the fetch fails', async () => {
        axios.get.mockRejectedValue(new Error('network error'));

        const wrapper = makeWrapper({ modelValue: 'web-site' });
        await flushPromises();

        expect(wrapper.findComponent(RemoteTypeSearchInput).props('modelValue')).toBe('Web Site');
    });

    it('emits update:modelValue with the picked slug when a type is selected', async () => {
        const wrapper = makeWrapper({ modelValue: null });
        await flushPromises();

        await wrapper.findComponent(RemoteTypeSearchInput).vm.$emit('select', { name: 'Zoom', slug: 'zoom' });

        expect(wrapper.emitted('update:modelValue')).toBeTruthy();
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['zoom']);
        expect(wrapper.findComponent(RemoteTypeSearchInput).props('modelValue')).toBe('Zoom');
    });
});
