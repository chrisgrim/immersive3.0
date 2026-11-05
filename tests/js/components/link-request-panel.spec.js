/**
 * What EventReview.vue sends with an approval. The panel's `decision` is the
 * `link` body of POST /api/admin/approve/events/{event}/approve: a wrong one
 * links a listing to the wrong show, or moves an address nobody confirmed.
 */
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import LinkRequestPanel from '@/PageComponents/Admin/Approval/LinkRequestPanel.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const sourceRequest = {
    id: 1,
    requested_by: { id: 2, name: 'Sam' },
    project: null,
    source_event: { id: 10, name: 'Flushment Day', organizer: { id: 3, name: 'St Anns' } },
};
const projectRequest = {
    id: 1,
    project: { id: 20, name: 'Ha Ha Ha', organizer: { id: 3, name: 'Pony Cam' } },
    source_event: null,
};
const matches = [
    { type: 'project', id: 20, name: 'Ha Ha Ha', organizer: { id: 3, name: 'Pony Cam' } },
    { type: 'listing', id: 30, name: 'Ha Ha', organizer: { id: 4, name: 'Other' } },
];

const panel = (event) => mount(LinkRequestPanel, { props: { event } });
const pick = (wrapper, value) => wrapper.find(`input[value="${value}"]`).setValue(true);
const chip = (wrapper, text) => wrapper.findAll('button').find((b) => b.text().includes(text));

describe('LinkRequestPanel.vue', () => {
    it('shows nothing, and decides nothing, for a listing with no request or matches', () => {
        const wrapper = panel({ link_request: null, name_matches: [] });

        expect(wrapper.text()).toBe('');
        expect(wrapper.vm.decision).toBeNull();
    });

    it('confirms by default when a request is pending', () => {
        expect(panel({ link_request: sourceRequest }).vm.decision).toEqual({ action: 'confirm' });
    });

    it('decides nothing for a name match until one is chosen', async () => {
        const wrapper = panel({ link_request: null, name_matches: matches });

        expect(wrapper.vm.decision).toBeNull();

        await chip(wrapper, 'Ha Ha Ha').trigger('click');
        expect(wrapper.vm.decision).toEqual({ action: 'change', project_id: 20 });

        await chip(wrapper, 'Ha Ha Ha').trigger('click');
        expect(wrapper.vm.decision).toBeNull();
    });

    it('sends a listing match as the source that makes the project', async () => {
        const wrapper = panel({ link_request: null, name_matches: matches });

        await chip(wrapper, 'Other').trigger('click');

        expect(wrapper.vm.decision).toEqual({ action: 'change', source_event_id: 30 });
    });

    it('the listing alone rejects the request', async () => {
        const wrapper = panel({ link_request: sourceRequest });

        await pick(wrapper, 'reject');

        expect(wrapper.vm.decision).toEqual({ action: 'reject' });
    });

    it('a change with nothing chosen decides nothing yet', async () => {
        const wrapper = panel({ link_request: sourceRequest, name_matches: matches });

        await pick(wrapper, 'change');

        expect(wrapper.vm.decision).toBeNull();
    });

    it('carries a maker correction', async () => {
        axios.get.mockResolvedValueOnce({ data: { data: [{ id: 9, name: 'Pony Cam' }] } });
        const wrapper = panel({ link_request: sourceRequest });

        await wrapper.find('input[placeholder="Correct who made it"]').setValue('Pony');
        await flushPromises();
        await chip(wrapper, 'Pony Cam').trigger('click');

        expect(wrapper.vm.decision).toEqual({ action: 'confirm', organizer_id: 9 });
        expect(wrapper.text()).toContain('Made by Pony Cam');
    });

    it('creates a bare organizer for a maker not on EI', async () => {
        axios.get.mockResolvedValueOnce({ data: { data: [] } });
        axios.post.mockResolvedValueOnce({ data: { organizer: { id: 11, name: 'New Co' } } });
        const wrapper = panel({ link_request: sourceRequest });

        await wrapper.find('input[placeholder="Correct who made it"]').setValue('New Co');
        await flushPromises();
        await chip(wrapper, 'as a new company').trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/admin/organizers/bare', { name: 'New Co' });
        expect(wrapper.vm.decision).toEqual({ action: 'confirm', organizer_id: 11 });
    });

    it('offers an address only when approving makes a project', async () => {
        const making = panel({ link_request: sourceRequest });
        const joining = panel({ link_request: projectRequest });
        const field = 'input[placeholder^="Address"]';

        expect(joining.find(field).exists()).toBe(false);

        await making.find(field).setValue('dracula-pony-cam');

        expect(making.vm.decision).toEqual({ action: 'confirm', slug: 'dracula-pony-cam' });
    });
});
