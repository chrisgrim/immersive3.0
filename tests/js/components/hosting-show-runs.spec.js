/** The hosting list folds a show run's runs into one group, with its own Edit. */
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import HostingDashboard from '@/PageComponents/Creation/index.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(() => Promise.resolve({ data: {} })), post: vi.fn(), delete: vi.fn() } }));

const past = (id, name, runOf = null) => ({
    id, slug: `e-${id}`, name, status: 'p', closingDate: '2024-01-31 00:00:00', isShowing: false, images: [], total_clicks: id === 2 ? 3 : 0, run_of: runOf,
});
const nona = { id: 7, name: 'Nona Show', slug: 'nona-show' };

function mountWith(events, kept = [{ id: 7, name: 'Nona Show', slug: 'nona-show', pending_requests: 2 }]) {
    return mount(HostingDashboard, {
        props: {
            organizer: { id: 1, name: 'Org', slug: 'org', events, kept_projects: kept },
            user: { id: 1, hasCreatedOrganizers: true },
        },
        global: { stubs: { teleport: true } },
    });
}

describe('show runs in the hosting list', () => {
    beforeEach(() => {
        delete window.location;
        window.location = { href: '', search: '', pathname: '/hosting/events' };
        try { localStorage.setItem('hosting.eventFilter', 'past'); } catch { /* no storage */ }
    });

    it('groups two or more runs, totals their clicks, and opens to show them', async () => {
        const wrapper = mountWith([past(1, 'Run A', nona), past(2, 'Run B', nona), past(3, 'Standalone')]);

        const group = wrapper.find('section');
        expect(group.text()).toContain('Nona Show');
        expect(group.text()).toContain('2 runs');
        expect(group.text()).toContain('2 asking to join');
        expect(group.find('a[href="/hosting/show-runs/nona-show/edit"]').exists()).toBe(true);
        expect(group.text()).not.toContain('Run A');
        expect(wrapper.text()).toContain('Standalone');

        await group.find('button[aria-expanded]').trigger('click');
        expect(wrapper.find('section').text()).toContain('Run A');
        expect(wrapper.find('section').text()).toContain('Run B');
    });

    it('a lone run still sits in its show run card, opened, and there is no Show Runs tab', () => {
        const wrapper = mountWith([past(1, 'Run A', nona), past(3, 'Standalone')]);

        const card = wrapper.find('section');
        expect(card.text()).toContain('Nona Show');
        expect(card.text()).toContain('Run A');
        expect(wrapper.text()).not.toContain('Show Runs');
    });

    it('lists a kept show run with no runs of this company under "Show runs you keep"', () => {
        const wrapper = mountWith([past(3, 'Standalone')], [{ id: 9, name: 'Elsewhere Show', slug: 'elsewhere-show', pending_requests: 0 }]);

        expect(wrapper.text()).toContain('Show runs you keep');
        expect(wrapper.find('a[href="/hosting/show-runs/elsewhere-show/edit"]').exists()).toBe(true);
    });

    it('"Add a run" makes the new run and its request in one call, and opens it at the start', async () => {
        axios.post.mockResolvedValueOnce({ data: { event: { slug: 'new-run' } } });
        const wrapper = mountWith([past(1, 'Run A', nona), past(2, 'Run B', nona)]);

        await wrapper.findAll('button').find((b) => b.text().includes('Add a run')).trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledTimes(1);
        expect(axios.post).toHaveBeenCalledWith('/hosting/event/create', { organizer_id: 1, project_id: 7 });
        expect(window.location.href).toBe('/hosting/event/new-run/edit');
    });

    it('a lone run of a kept show run still shows who is asking to join', () => {
        const wrapper = mountWith([past(1, 'Run A', nona), past(3, 'Standalone')]);

        expect(wrapper.find('section').text()).toContain('2 asking to join');
    });

    it('a copy waiting to join sits in its show run\'s card, marked as waiting', async () => {
        // In the same list as the runs (here Past), but only asking to join.
        const copy = { ...past(4, 'Run B (Copy)'), run_of: null, joining: nona };
        const wrapper = mountWith([past(1, 'Run A', nona), past(2, 'Run B', nona), copy]);

        const group = wrapper.find('section');
        expect(group.text()).toContain('2 runs');
        expect(group.text()).toContain('1 waiting to join');

        await group.find('button[aria-expanded]').trigger('click');
        expect(wrapper.find('section').text()).toContain('Run B (Copy)');
        expect(wrapper.find('section').text()).toContain('Waiting to join');
    });

    it('a lone copy waiting to join sits in its show run card, marked as waiting', () => {
        const copy = { ...past(4, 'Run B (Copy)'), run_of: null, joining: nona };
        const wrapper = mountWith([copy]);

        expect(wrapper.find('section').text()).toContain('Nona Show');
        expect(wrapper.find('section').text()).toContain('Waiting to join');
    });

    it('remembers an opened show run card in this browser', async () => {
        localStorage.removeItem('hosting.openShowRuns');
        const events = [past(1, 'Run A', nona), past(2, 'Run B', nona)];

        const first = mountWith(events);
        await first.find('section button[aria-expanded]').trigger('click');
        expect(JSON.parse(localStorage.getItem('hosting.openShowRuns'))).toEqual(['show-7']);
        first.unmount();

        const again = mountWith(events);
        expect(again.find('section').text()).toContain('Run A');
    });
});
