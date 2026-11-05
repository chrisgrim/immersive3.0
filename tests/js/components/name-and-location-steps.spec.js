/**
 * The Name step's join and the Location step's venue picker: what they ask,
 * in Kathryn's exact words, and what they send.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { reactive } from 'vue';
import axios from 'axios';
import ShowMatch from '@/PageComponents/Creation/Core/Components/show-match.vue';
import VenuePicker from '@/PageComponents/Creation/Core/Components/venue-picker.vue';
import ConfirmDetails from '@/PageComponents/Creation/Core/Components/confirm-details.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }));

const QUESTION = 'Is this a new run of one of these? A new city, new dates, or a remount.';

const withEvent = (component, event) => mount(component, { global: { provide: { event: reactive(event) } } });

const venue = { id: 7, name: 'The Goodman', address: '170 N Dearborn St, Chicago', street: 'N Dearborn St', city: 'Chicago', home: '170', latitude: 41.8, longitude: -87.6 };

describe('show-match.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers();
    });
    afterEach(() => vi.useRealTimers());

    const settle = async () => {
        await vi.advanceTimersByTimeAsync(600);
        await flushPromises();
    };

    it('asks the question in Kathryn\'s words when a similar show is found', async () => {
        axios.get.mockResolvedValue({ data: [{ type: 'project', id: 3, name: 'Ha Ha Ha' }] });
        const wrapper = withEvent(ShowMatch, { id: 1, slug: 'x', name: 'Ha Ha Ha', status: '1' });

        await settle();

        expect(wrapper.text()).toContain(QUESTION);
    });

    it('asks nothing with no match, and nothing of a live listing', async () => {
        axios.get.mockResolvedValue({ data: [] });
        const draft = withEvent(ShowMatch, { id: 1, slug: 'x', name: 'Ha Ha Ha', status: '1' });
        await settle();
        expect(draft.text()).not.toContain(QUESTION);

        const live = withEvent(ShowMatch, { id: 1, slug: 'x', name: 'Ha Ha Ha', status: 'p' });
        await settle();
        expect(live.text()).not.toContain(QUESTION);
        expect(axios.get).toHaveBeenCalledTimes(1);
    });

    it('with a request, says what it will join and offers remove', async () => {
        axios.delete.mockResolvedValue({});
        const wrapper = withEvent(ShowMatch, { slug: 'x', name: 'Ha', status: '1', link_request: { show: 'Ha Ha Ha' } });

        expect(wrapper.text()).toContain('This will join Ha Ha Ha');
        expect(wrapper.find('[data-run-fields]').exists()).toBe(true);

        await wrapper.find('[data-joining] button').trigger('click');
        await flushPromises();

        expect(axios.delete).toHaveBeenCalledWith('/hosting/event/x/link-request');
        expect(wrapper.text()).not.toContain('This will join');
    });

    it('keeps a run\'s fields away from a plain listing', () => {
        expect(withEvent(ShowMatch, { slug: 'x', name: '', status: 'p' }).find('[data-run-fields]').exists()).toBe(false);
    });
});

describe('venue-picker.vue', () => {
    beforeEach(() => vi.clearAllMocks());

    it('offers suggestions with their address, and picking fills the address in', async () => {
        vi.useFakeTimers();
        axios.get.mockImplementation((url) => Promise.resolve({ data: url.includes('match') ? { venue: null } : [venue] }));
        const event = { location: { venue: '', venue_id: null } };
        const onPicked = vi.fn();
        const wrapper = mount(VenuePicker, { props: { onPicked }, global: { provide: { event: reactive(event) } } });

        await wrapper.find('[data-venue-input]').setValue('Good');
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();
        vi.useRealTimers();

        expect(wrapper.text()).toContain('170 N Dearborn St, Chicago');

        await wrapper.find('[data-venue-suggestions] button').trigger('click');

        expect(onPicked).toHaveBeenCalled();
        expect(wrapper.find('[data-venue-linked]').exists()).toBe(true);
    });

    it('typing over a picked venue takes it off', async () => {
        axios.get.mockResolvedValue({ data: { venue: null } });
        const wrapper = withEvent(VenuePicker, { location: { venue: 'The Goodman', venue_id: 7, street: 'A', city: 'B' } });
        await flushPromises();

        await wrapper.find('[data-venue-input]').setValue('The Other');

        expect(wrapper.find('[data-venue-linked]').exists()).toBe(false);
    });

    it('matches a known address the other way round', async () => {
        axios.get.mockResolvedValue({ data: { venue } });
        const wrapper = withEvent(VenuePicker, { location: { venue: '', venue_id: null, street: 'N Dearborn St', city: 'Chicago', home: '170' } });
        await flushPromises();

        expect(wrapper.find('[data-venue-known]').text()).toContain('Is this The Goodman?');
    });

    it('offers nothing for a secret location', async () => {
        const wrapper = withEvent(VenuePicker, { location: { venue: 'Good', venue_id: null, hiddenLocationToggle: true, street: 'A', city: 'B' } });
        await flushPromises();

        await wrapper.find('[data-venue-input]').setValue('Goodman');

        expect(axios.get).not.toHaveBeenCalled();
        expect(wrapper.find('[data-venue-suggestions]').exists()).toBe(false);
    });
});

describe('confirm-details.vue', () => {
    it('shows only with a request, and confirms through the server', async () => {
        axios.post.mockResolvedValue({});
        expect(withEvent(ConfirmDetails, { slug: 'x' }).find('[data-confirm-details]').exists()).toBe(false);

        const wrapper = withEvent(ConfirmDetails, { slug: 'x', link_request: { show: 'Ha Ha Ha', details_confirmed: false } });
        await wrapper.find('input').trigger('change');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/hosting/event/x/link-request/confirm');
        expect(wrapper.find('input').element.disabled).toBe(true);
    });
});
