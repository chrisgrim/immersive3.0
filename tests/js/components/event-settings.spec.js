/**
 * The event editor's Settings (the card above Name): Duplicate, its show run
 * (add it to one, or take it out) and Delete behind Airbnb's pop-up.
 */
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import { reactive } from 'vue';
import Settings from '@/PageComponents/Creation/Core/Pages/settings.vue';

vi.mock('axios', () => ({
    default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));

const mountWith = (overrides = {}) => mount(Settings, {
    global: {
        provide: { event: reactive({ slug: 'nona-2026', name: 'Nona 2026', images: [], organizer: { slug: 'nona' }, link_request: null, run_of: null, ...overrides }) },
        stubs: { teleport: true },
    },
});

describe('event settings', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        delete window.location;
        window.location = { href: '', reload: vi.fn() };
    });

    it('duplicates through the pop-up: a live event is asked if the copy is a new run', async () => {
        axios.get.mockResolvedValue({ data: { choices: [] } });
        axios.post.mockResolvedValueOnce({ data: { event: { slug: 'nona-2026-copy' } } });
        const wrapper = mountWith({ status: 'p' });
        await wrapper.find('[data-settings-duplicate]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-duplicate-run]').trigger('click');
        await wrapper.find('[data-duplicate-yes]').trigger('click');
        await flushPromises();
        expect(axios.post).toHaveBeenCalledWith('/api/events/nona-2026/duplicate', { as_run: true });
        expect(window.location.href).toBe('/hosting/event/nona-2026-copy/edit');
    });

    it('no show run to join: no Add to a show run card', async () => {
        axios.get.mockResolvedValueOnce({ data: { choices: [] } });
        const wrapper = mountWith();
        await flushPromises();
        expect(wrapper.find('[data-settings-add-run]').exists()).toBe(false);
    });

    it('not in a show run: lists the choices and asks to join the one picked', async () => {
        axios.get.mockResolvedValueOnce({ data: { choices: [{ id: 3, name: 'Nona', image: null }] } });
        axios.post.mockResolvedValueOnce({ data: {} });
        const wrapper = mountWith();
        await flushPromises();
        await wrapper.find('[data-settings-add-run]').trigger('click');
        await wrapper.find('[data-settings-run-choice]').trigger('click');
        await flushPromises();
        expect(axios.post).toHaveBeenCalledWith('/hosting/event/nona-2026/link-request', { project_id: 3 });
        expect(window.location.reload).toHaveBeenCalled();
    });

    it('part of a show run it keeps: Remove takes it out', async () => {
        axios.delete.mockResolvedValueOnce({ data: {} });
        const wrapper = mountWith({ run_of: { id: 3, name: 'Nona', slug: 'nona-show', kept: true } });
        expect(wrapper.find('[data-settings-run]').text()).toContain('In a show run');
        await wrapper.find('[data-settings-remove-run]').trigger('click');
        await flushPromises();
        expect(axios.delete).toHaveBeenCalledWith('/hosting/show-runs/nona-show/instances/nona-2026');
    });

    it("part of someone else's show run: no Remove", () => {
        const wrapper = mountWith({ run_of: { id: 3, name: 'Nona', slug: 'nona-show', kept: false } });
        expect(wrapper.find('[data-settings-remove-run]').exists()).toBe(false);
    });

    it('asking to join one: Remove withdraws the request', async () => {
        axios.delete.mockResolvedValueOnce({ data: {} });
        const wrapper = mountWith({ link_request: { id: 8, show: 'Nona', project_id: 3 } });
        expect(wrapper.find('[data-settings-run]').text()).toContain('Waiting to join a show run');
        await wrapper.find('[data-settings-remove-run]').trigger('click');
        await flushPromises();
        expect(axios.delete).toHaveBeenCalledWith('/hosting/event/nona-2026/link-request');
    });

    it('delete asks first, then goes back to the events list', async () => {
        axios.delete.mockResolvedValueOnce({ data: {} });
        const wrapper = mountWith();
        await wrapper.find('[data-settings-delete]').trigger('click');
        expect(wrapper.find('[data-settings-delete-ask]').text()).toContain('Delete Nona 2026?');
        expect(axios.delete).not.toHaveBeenCalled();

        await wrapper.find('[data-settings-delete-yes]').trigger('click');
        await flushPromises();
        expect(axios.delete).toHaveBeenCalledWith('/hosting/event/nona-2026');
        expect(window.location.href).toBe('/hosting/events?organizer=nona');
    });
});
