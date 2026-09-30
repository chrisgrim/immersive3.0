/**
 * Specs for Nav/Components/recent-searches-list.vue — the rows at the top of
 * the nav search dropdown. An account's rows pin in place and can be edited;
 * a guest's one row (kept in their browser) pins by asking them to log in.
 */
import { vi } from 'vitest';
import { mount } from '@vue/test-utils';
import axios from 'axios';
import RecentSearchesList from '@/PageComponents/Nav/Components/recent-searches-list.vue';

vi.mock('axios', () => ({ default: { patch: vi.fn(), post: vi.fn() } }));

const accountRow = { id: 5, name: 'New York', criteria: { city: 'New York' }, url: '/index/search?city=New+York', pinned: false };
const guestRow = { id: 'guest', guest: true, name: 'New York', criteria: { city: 'New York' }, url: '/index/search/replay?criteria=x', pinned: false };

beforeEach(() => {
    axios.patch.mockReset();
    window.localStorage.clear();
    window.localStorage.setItem('ei_guest_search', JSON.stringify({ name: 'New York', criteria: { city: 'New York' }, pinRequestedAt: null }));
});

const mountList = (searches) => mount({
    components: { RecentSearchesList },
    template: '<ul><RecentSearchesList :searches="searches" /></ul>',
    data: () => ({ searches }),
});

describe('recent-searches-list', () => {
    it('pins an account row in place', async () => {
        window.Laravel = { user: { id: 1 } };
        axios.patch.mockResolvedValueOnce({ data: { search: { pinned: true } } });
        const wrapper = mountList([accountRow]);

        await wrapper.find('button[aria-label="Pin search"]').trigger('click');

        expect(axios.patch).toHaveBeenCalledWith('/api/hub/saved-searches/5/pin');
        expect(wrapper.text()).toContain('Edit');
    });

    it('opens the login modal when a guest pins, and remembers to pin it', async () => {
        window.Laravel = {};
        const opened = vi.fn();
        window.addEventListener('open-login-modal', opened);
        const wrapper = mountList([guestRow]);

        await wrapper.find('button[aria-label="Log in or sign up to pin this search"]').trigger('click');

        expect(opened).toHaveBeenCalledOnce();
        expect(axios.patch).not.toHaveBeenCalled();
        expect(JSON.parse(window.localStorage.getItem('ei_guest_search')).pinRequestedAt).not.toBeNull();
        window.removeEventListener('open-login-modal', opened);
    });

    it('offers a guest no Edit, which needs an account', () => {
        window.Laravel = {};
        const wrapper = mountList([guestRow]);

        expect(wrapper.text()).not.toContain('Edit');
    });
});
