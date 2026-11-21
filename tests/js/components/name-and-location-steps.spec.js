/**
 * The Name step's join and the Location step's venue picker: what they ask,
 * in the team's exact words, and what they send.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { reactive } from 'vue';
import axios from 'axios';
import ShowMatch from '@/PageComponents/Creation/Core/Components/show-match.vue';
import VenueSearch from '@/PageComponents/Creation/Core/Components/venue-search.vue';
import ConfirmDetails from '@/PageComponents/Creation/Core/Components/confirm-details.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }));

const QUESTION = "There's another event named this. Is this a new run of one of the following, with a new location, new dates, or other updates?";

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

    it('asks the question in Noah\'s words when a similar show is found', async () => {
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

    it('with a request, leaves the joining to the bottom bar picker and asks nothing', () => {
        const wrapper = withEvent(ShowMatch, { slug: 'x', name: 'Ha', status: '1', link_request: { show: 'Ha Ha Ha' } });

        expect(wrapper.text()).not.toContain('This will join');
        expect(wrapper.find('[data-match-question]').exists()).toBe(false);
        expect(wrapper.find('[data-run-fields]').exists()).toBe(false);
    });

    it('keeps a run\'s fields away from a plain listing', () => {
        expect(withEvent(ShowMatch, { slug: 'x', name: '', status: 'p' }).find('[data-run-fields]').exists()).toBe(false);
    });
});

describe('venue-search.vue', () => {
    beforeEach(() => vi.clearAllMocks());

    it('lists venues with their address and hands back the one picked', async () => {
        axios.get.mockResolvedValue({ data: [{ id: 7, name: 'The Goodman', room: null, address: '170 N Dearborn St, Chicago' }] });
        const onPicked = vi.fn();
        const wrapper = mount(VenueSearch, { props: { onPicked } });

        await wrapper.find('[data-venue-search]').setValue('Good');
        await wrapper.find('[data-venue-search]').trigger('input');
        await new Promise((resolve) => setTimeout(resolve, 350));
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/venues/search', { params: { keywords: 'Good' } });
        expect(wrapper.text()).toContain('170 N Dearborn St, Chicago');
        await wrapper.find('[data-venue-results] button').trigger('click');
        expect(onPicked).toHaveBeenCalledWith(expect.objectContaining({ id: 7 }));
    });

    it('offers to add a venue it does not know, by the name typed', async () => {
        axios.get.mockResolvedValue({ data: [] });
        const onAdd = vi.fn();
        const wrapper = mount(VenueSearch, { props: { onAdd, initial: 'The Lowry' } });
        await flushPromises();

        await wrapper.find('[data-venue-add]').trigger('click');
        expect(onAdd).toHaveBeenCalledWith('The Lowry');
    });

    it('a name EI already has can still be added as a different venue (another city, another space)', async () => {
        axios.get.mockResolvedValue({ data: [{ id: 7, name: 'The Lowry', address: 'Salford' }] });
        const wrapper = mount(VenueSearch, { props: { initial: 'the lowry' } });
        await flushPromises();

        expect(wrapper.find('[data-venue-add]').text()).toContain('A different');
        await wrapper.find('[data-venue-add]').trigger('click');
        expect(wrapper.emitted('add')[0]).toEqual(['the lowry']);
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

describe('name.vue, a run of a show run', () => {
    it('says whose run it is instead of asking for a unique name', async () => {
        const { default: Name } = await import('@/PageComponents/Creation/Core/Pages/name.vue');
        const wrapper = withEvent(Name, { slug: 'x', name: '', status: '1', link_request: { show: '7 Paintings' } });

        expect(wrapper.text()).toContain('This is a run of 7 Paintings');
        expect(wrapper.text()).toContain(`7 Paintings (Texas or ${new Date().getFullYear() + 1})`);
        expect(wrapper.text()).not.toContain('Enter a unique name');
    });
});
