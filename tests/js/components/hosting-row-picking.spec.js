/**
 * The hosting events list, Airbnb's listings page: the whole row opens an
 * event where it left off (only one EI is looking at, or a deleted one, opens
 * a pop-up), a box on the left picks it and opens the side panel (one picked:
 * Edit, Duplicate, Add to a show run, Delete; several: Add to a show run and Delete), and a
 * long hover brings Edit and Delete onto the row.
 */
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import HostingDashboard from '@/PageComponents/Creation/index.vue';

vi.mock('axios', () => ({
    default: { get: vi.fn(() => Promise.resolve({ data: {} })), post: vi.fn(), delete: vi.fn(() => Promise.resolve({ data: {} })) },
}));

const draft = { id: 1, slug: 'draft-one', name: 'Draft One', status: '5', images: [], location: { city: 'Petaluma', region: 'CA' } };
const live = { id: 2, slug: 'live-two', name: 'Live Two', status: 'p', isShowing: true, images: [] };
const other = { id: 3, slug: 'draft-three', name: 'Draft Three', status: '2', images: [] };

// keptProjects: the show runs this company keeps (Add to a show run shows only once it has one).
function mountDashboard(events = [draft, live, other], keptProjects = [{ id: 9, name: 'The Big Show', slug: 'the-big-show' }]) {
    return mount(HostingDashboard, {
        props: {
            organizer: { id: 1, name: 'Test Org', slug: 'test-org', events, kept_projects: keptProjects },
            user: { id: 1, isAdmin: false, isModerator: false, hasCreatedOrganizers: true },
        },
        global: { stubs: { teleport: true } },
    });
}

const row = (wrapper, id) => wrapper.find(`[data-event-row="${id}"]`);

describe('the hosting events list', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        delete window.location;
        window.location = { href: '', search: '', pathname: '/hosting/events', reload: vi.fn() };
        window.confirm = vi.fn(() => true);
        try { localStorage.clear(); } catch { /* private window */ }
    });

    it('a published event opens its edit page from anywhere on the row', async () => {
        const wrapper = mountDashboard();
        await row(wrapper, 2).trigger('click');
        expect(window.location.href).toBe('/hosting/event/live-two/edit');
    });

    it('a draft opens where it left off, like a published one, with no pop-up', async () => {
        const wrapper = mountDashboard();
        await row(wrapper, 1).trigger('click');
        expect(window.location.href).toBe('/hosting/event/draft-one/edit?view=Description');
        expect(wrapper.find('[data-event-pop]').exists()).toBe(false);
    });

    it('one EI is looking at opens the pop-up: no Edit, only Delete', async () => {
        const wrapper = mountDashboard([{ ...draft, status: 'r' }]);
        await row(wrapper, 1).trigger('click');
        const pop = wrapper.find('[data-event-pop]');
        expect(pop.text()).toContain('A moderator is looking at it');
        expect(pop.find('[data-pop-edit]').exists()).toBe(false);
        expect(window.location.href).toBe('');
    });

    it('delete asks in its own pop-up (not the browser box): Cancel keeps it, Delete deletes it', async () => {
        const wrapper = mountDashboard();
        await row(wrapper, 1).find('[data-row-delete]').trigger('click');
        const ask = wrapper.find('[data-delete-ask]');
        expect(ask.text()).toContain('Delete Draft One?');
        expect(ask.text()).toContain('Petaluma, CA');
        expect(window.confirm).not.toHaveBeenCalled();

        await ask.find('[data-delete-no]').trigger('click');
        expect(wrapper.find('[data-delete-ask]').exists()).toBe(false);
        expect(axios.delete).not.toHaveBeenCalled();

        await row(wrapper, 1).find('[data-row-delete]').trigger('click');
        await wrapper.find('[data-delete-yes]').trigger('click');
        await flushPromises();
        expect(axios.delete).toHaveBeenCalledWith('/hosting/event/draft-one');
        expect(window.location.reload).toHaveBeenCalled();
    });

    it('a delete that fails says so in the pop-up and stays', async () => {
        axios.delete.mockRejectedValueOnce({ response: { status: 500 } });
        const wrapper = mountDashboard();
        await row(wrapper, 1).find('[data-row-delete]').trigger('click');
        await wrapper.find('[data-delete-yes]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-delete-ask]').text()).toContain('That did not delete');
        expect(window.location.reload).not.toHaveBeenCalled();
    });

    it('picking one opens the panel with Edit, Duplicate, Add to a show run and Delete', async () => {
        const wrapper = mountDashboard();
        expect(wrapper.find('[data-pick-panel]').exists()).toBe(false);

        await row(wrapper, 1).find('[data-event-pick]').trigger('change');
        const panel = wrapper.find('[data-pick-panel]');
        expect(panel.text()).toContain('Edit 1 event');
        ['[data-panel-edit]', '[data-panel-duplicate]', '[data-panel-add-run]', '[data-panel-delete]'].forEach((part) => {
            expect(panel.find(part).exists()).toBe(true);
        });
        // Picking does not open the row.
        expect(window.location.href).toBe('');
        expect(wrapper.find('[data-event-pop]').exists()).toBe(false);
    });

    it('Add to a show run lists the choices and asks to join the one picked', async () => {
        axios.get.mockResolvedValueOnce({ data: { choices: [{ id: 9, name: 'The Big Show', image: null }] } });
        axios.post.mockResolvedValueOnce({ data: {} });
        const wrapper = mountDashboard();
        await row(wrapper, 1).find('[data-event-pick]').trigger('change');
        await wrapper.find('[data-panel-add-run]').trigger('click');
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/hosting/event/draft-one/show-runs');
        await wrapper.find('[data-run-choice]').trigger('click');
        await flushPromises();
        expect(axios.post).toHaveBeenCalledWith('/hosting/event/draft-one/link-request', { project_id: 9 });
        expect(window.location.reload).toHaveBeenCalled();
    });

    it('several picked: Delete only, for all of them', async () => {
        const wrapper = mountDashboard();
        await row(wrapper, 1).find('[data-event-pick]').trigger('change');
        await row(wrapper, 3).find('[data-event-pick]').trigger('change');
        const panel = wrapper.find('[data-pick-panel]');

        expect(panel.text()).toContain('2 events picked');
        expect(panel.find('[data-panel-duplicate]').exists()).toBe(false);
        expect(panel.find('[data-panel-edit]').exists()).toBe(false);

        await panel.find('[data-panel-delete-all]').trigger('click');
        expect(wrapper.find('[data-delete-ask]').text()).toContain('Delete 2 events?');
        await wrapper.find('[data-delete-yes]').trigger('click');
        await flushPromises();
        expect(axios.delete).toHaveBeenCalledWith('/hosting/event/draft-one');
        expect(axios.delete).toHaveBeenCalledWith('/hosting/event/draft-three');
        expect(window.location.reload).toHaveBeenCalled();
    });

    it('no show run yet: no Add to a show run, alone or several (Duplicate starts one)', async () => {
        const wrapper = mountDashboard([draft, live, other], []);
        await row(wrapper, 1).find('[data-event-pick]').trigger('change');
        expect(wrapper.find('[data-panel-add-run]').exists()).toBe(false);
        await row(wrapper, 3).find('[data-event-pick]').trigger('change');
        expect(wrapper.find('[data-panel-add-run-all]').exists()).toBe(false);
    });

    it('Duplicate (from the hover too) opens its own pop-up, with a small Also start a show run box', async () => {
        axios.get.mockResolvedValueOnce({ data: { choices: [{ id: 9, name: 'The Big Show', image: null }] } });
        axios.post.mockResolvedValueOnce({ data: { event: { slug: 'live-two-copy' } } });
        const wrapper = mountDashboard();
        await row(wrapper, 2).find('[data-row-duplicate]').trigger('click');
        await flushPromises();
        const ask = () => wrapper.find('[data-duplicate-ask]');
        expect(ask().text()).toContain('Same show, new run?');
        // Only this event's own show: never another show run.
        expect(ask().text()).not.toContain('The Big Show');
        expect(window.location.href).toBe('');
        expect(ask().find('[data-duplicate-run]').attributes('aria-checked')).toBe('false');

        await ask().find('[data-duplicate-run]').trigger('click');
        await ask().find('[data-duplicate-yes]').trigger('click');
        await flushPromises();
        expect(axios.post).toHaveBeenCalledWith('/api/events/live-two/duplicate', { as_run: true });
        expect(window.location.href).toBe('/hosting/event/live-two-copy/edit');
    });

    it('a draft: just a copy, no show run box', async () => {
        axios.get.mockResolvedValueOnce({ data: { choices: [] } });
        axios.post.mockResolvedValueOnce({ data: { event: { slug: 'draft-one-copy' } } });
        const wrapper = mountDashboard();
        await row(wrapper, 1).find('[data-row-duplicate]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-duplicate-run]').exists()).toBe(false);
        await wrapper.find('[data-duplicate-yes]').trigger('click');
        await flushPromises();
        expect(axios.post).toHaveBeenCalledWith('/api/events/draft-one/duplicate', { as_run: false });
    });

    it('several picked: add them all to one show run, leaving out one already joining', async () => {
        axios.get.mockResolvedValueOnce({ data: { choices: [{ id: 9, name: 'The Big Show', image: null }] } });
        axios.post.mockResolvedValue({ data: {} });
        const joining = { id: 4, slug: 'joining-four', name: 'Joining Four', status: '3', images: [], joining: { id: 9, name: 'The Big Show' } };
        const wrapper = mountDashboard([draft, live, other, joining]);
        for (const id of [1, 3, 4]) await row(wrapper, id).find('[data-event-pick]').trigger('change');

        const add = wrapper.find('[data-panel-add-run-all]');
        expect(add.text()).toContain('Add 2 of them to a show run');
        await add.trigger('click');
        await flushPromises();
        await wrapper.find('[data-run-choice]').trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledTimes(2);
        expect(axios.post).toHaveBeenCalledWith('/hosting/event/draft-one/link-request', { project_id: 9 });
        expect(axios.post).toHaveBeenCalledWith('/hosting/event/draft-three/link-request', { project_id: 9 });
        expect(window.location.reload).toHaveBeenCalled();
        axios.post.mockReset();
    });

    it('several picked: remove them from their show runs, only where that is theirs to do', async () => {
        const joining = { id: 4, slug: 'joining-four', name: 'Joining Four', status: '3', images: [], joining: { id: 9, name: 'Big Show', slug: 'big-show' } };
        const kept = { id: 5, slug: 'run-five', name: 'Run Five', status: 'p', isShowing: true, images: [], run_of: { id: 9, name: 'Big Show', slug: 'big-show' } };
        const elsewhere = { id: 6, slug: 'run-six', name: 'Run Six', status: 'p', isShowing: true, images: [], run_of: { id: 12, name: 'Their Show', slug: 'their-show' } };
        const wrapper = mount(HostingDashboard, {
            props: {
                organizer: { id: 1, name: 'Test Org', slug: 'test-org', events: [joining, kept, elsewhere, draft], kept_projects: [{ id: 9, name: 'Big Show', slug: 'big-show' }] },
                user: { id: 1, isAdmin: false, isModerator: false, hasCreatedOrganizers: true },
            },
            global: { stubs: { teleport: true } },
        });
        // 4 and 5 are runs of the same show: its card opens from anywhere on its top.
        await wrapper.find('[data-show-run-head]').trigger('click');
        for (const id of [4, 5, 6, 1]) await row(wrapper, id).find('[data-event-pick]').trigger('change');

        const remove = wrapper.find('[data-panel-remove-runs]');
        expect(remove.text()).toContain('Remove 2 events from show runs');
        await remove.trigger('click');
        await flushPromises();

        expect(axios.delete).toHaveBeenCalledTimes(2);
        expect(axios.delete).toHaveBeenCalledWith('/hosting/event/joining-four/link-request');
        expect(axios.delete).toHaveBeenCalledWith('/hosting/show-runs/big-show/instances/run-five');
        expect(window.location.reload).toHaveBeenCalled();
    });

    it('while picking, clicking a row picks or unpicks it and never opens it', async () => {
        const wrapper = mountDashboard();
        await row(wrapper, 1).find('[data-event-pick]').trigger('change');
        await row(wrapper, 3).trigger('click');
        expect(wrapper.find('[data-pick-panel]').text()).toContain('2 events picked');

        await row(wrapper, 3).trigger('click');
        await row(wrapper, 1).trigger('click');
        expect(wrapper.find('[data-pick-panel]').exists()).toBe(false);
        expect(window.location.href).toBe('');

        // Nothing picked: the row opens again.
        await row(wrapper, 1).trigger('click');
        expect(window.location.href).toBe('/hosting/event/draft-one/edit?view=Description');
    });

    it('pick all, and close the panel', async () => {
        const wrapper = mountDashboard();
        await row(wrapper, 1).find('[data-event-pick]').trigger('change');
        await wrapper.find('[data-pick-all]').trigger('click');
        expect(wrapper.find('[data-pick-panel]').text()).toContain('3 events picked');

        await wrapper.find('[data-pick-close]').trigger('click');
        expect(wrapper.find('[data-pick-panel]').exists()).toBe(false);
    });

    it('Edit and Delete sit on the row for hovering, each its own click', async () => {
        const wrapper = mountDashboard();
        expect(row(wrapper, 1).find('[data-row-tools]').classes()).toContain('md:group-hover/row:flex');

        await row(wrapper, 1).find('[data-row-edit]').trigger('click');
        expect(window.location.href).toBe('/hosting/event/draft-one/edit?view=Description');
        window.location.href = '';

        await row(wrapper, 1).find('[data-row-delete]').trigger('click');
        // Its own click, not the row's: the delete question, not the event's pop-up.
        expect(wrapper.find('[data-event-pop]').exists()).toBe(false);
        expect(wrapper.find('[data-delete-ask]').exists()).toBe(true);
    });
});
