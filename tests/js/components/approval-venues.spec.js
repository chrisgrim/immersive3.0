/** Approval Queue > Venues: every waiting venue can be reached, page by page. */
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import Venues from '@/PageComponents/Admin/Approval/Venues.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const pageOf = (page, last) => ({ data: { data: [{ id: page, slug: `v-${page}`, name: `Venue ${page}` }], current_page: page, last_page: last } });

describe('Approval Venues.vue', () => {
    beforeEach(() => vi.clearAllMocks());

    it('steps to the next page of waiting venues', async () => {
        axios.get.mockResolvedValueOnce(pageOf(1, 2)).mockResolvedValueOnce(pageOf(2, 2));
        const wrapper = mount(Venues);
        await flushPromises();

        expect(wrapper.text()).toContain('Page 1 of 2');
        await wrapper.findAll('button').find((b) => b.text() === 'Next').trigger('click');
        await flushPromises();

        expect(axios.get).toHaveBeenLastCalledWith('/api/admin/venues', { params: { status: 'pending', page: 2 } });
        expect(wrapper.text()).toContain('Venue 2');
        expect(wrapper.text()).toContain('Page 2 of 2');
    });

    it('shows no page controls when everything fits on one page', async () => {
        axios.get.mockResolvedValueOnce(pageOf(1, 1));
        const wrapper = mount(Venues);
        await flushPromises();

        expect(wrapper.text()).not.toContain('Page 1 of 1');
    });
});
