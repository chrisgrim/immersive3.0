/**
 * The keeper's page and a run's credits panel: minimal screens, so these only
 * pin what they send and that a refusal is shown rather than swallowed.
 */
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import ProjectEdit from '@/PageComponents/Projects/project-edit.vue';
import EventCredits from '@/PageComponents/Projects/event-credits.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }));

const project = (overrides = {}) => ({
    name: 'Ha Ha Ha',
    slug: 'ha-ha-ha',
    description: null,
    organizer: { id: 1, name: 'Pony Cam' },
    runs: [{ id: 5, name: 'Chicago', slug: 'ha-ha-ha', public_url: '/events/ha-ha-ha/chicago-2026' }],
    writers: [],
    aggregate: [],
    link_requests: [],
    photos: [],
    max_photos: 5,
    public_url: '/projects/ha-ha-ha',
    ...overrides,
});

const panel = (overrides = {}) => ({
    event: { id: 5, name: 'Chicago', slug: 'ha-ha-ha' },
    credits: [],
    show_writers: [],
    editable_departments: ['cast'],
    kinds: ['regular', 'swing'],
    special_thanks: null,
    can_edit_thanks: false,
    can_copy: false,
    other_runs: [],
    ...overrides,
});

const button = (wrapper, text) => wrapper.findAll('button').find((b) => b.text() === text);

describe('project-edit.vue', () => {
    beforeEach(() => vi.clearAllMocks());

    it('approves a request through the show it belongs to', async () => {
        axios.post.mockResolvedValueOnce({ data: project() });
        const wrapper = mount(ProjectEdit, {
            props: { project: project({ link_requests: [{ id: 7, event: { name: 'Rome', status: 'p' } }] }) },
        });

        await button(wrapper, 'Approve').trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/hosting/projects/ha-ha-ha/link-requests/7/approve');
        expect(wrapper.text()).not.toContain('Rome');
    });

    it('will not approve a listing still in review', () => {
        const wrapper = mount(ProjectEdit, {
            props: { project: project({ link_requests: [{ id: 7, event: { name: 'Rome', status: 'r' } }] }) },
        });

        expect(button(wrapper, 'Approve').attributes('disabled')).toBeDefined();
    });

    it('shows why a save was refused', async () => {
        axios.post.mockRejectedValueOnce({ response: { data: { errors: { name: ['The name is required.'] } } } });
        const wrapper = mount(ProjectEdit, { props: { project: project() } });

        await button(wrapper, 'Update').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('The name is required.');
    });
});

describe('project-edit.vue, a rename then a failed photo save', () => {
    beforeEach(() => vi.clearAllMocks());

    // The photo box, reduced to what the page asks of it: a pending change.
    const galleryStub = {
        name: 'PhotoGalleryEdit',
        template: '<div />',
        setup(_, { expose }) {
            expose({ changed: true, formData: () => new FormData(), reset: vi.fn() });
        },
    };

    it('follows the new address at once, so the retry goes to it', async () => {
        axios.post
            .mockResolvedValueOnce({ data: project({ name: 'Ha Ha Ha Too', slug: 'ha-ha-ha-too' }) })
            .mockRejectedValueOnce({ response: { data: { message: 'The picture could not be saved.' } } })
            .mockResolvedValueOnce({ data: project({ name: 'Ha Ha Ha Too', slug: 'ha-ha-ha-too' }) });
        const wrapper = mount(ProjectEdit, { props: { project: project() }, global: { stubs: { PhotoGalleryEdit: galleryStub } } });

        await button(wrapper, 'Update').trigger('click');
        await flushPromises();

        expect(axios.post.mock.calls[0][0]).toBe('/hosting/projects/ha-ha-ha');
        expect(axios.post.mock.calls[1][0]).toBe('/hosting/projects/ha-ha-ha-too/photos');
        expect(wrapper.text()).toContain('The picture could not be saved.');

        await button(wrapper, 'Update').trigger('click');
        await flushPromises();

        expect(axios.post.mock.calls[2][0]).toBe('/hosting/projects/ha-ha-ha-too');
    });
});

describe('event-credits.vue', () => {
    beforeEach(() => vi.clearAllMocks());

    it('offers only the departments the policy allows', () => {
        const wrapper = mount(EventCredits, { props: { panel: panel() } });

        expect(wrapper.findAll('option').map((o) => o.text())).toContain('cast');
        expect(wrapper.findAll('option').map((o) => o.text())).not.toContain('production');
    });

    it('hides the add form from someone who can edit nothing', () => {
        const wrapper = mount(EventCredits, { props: { panel: panel({ editable_departments: [] }) } });

        expect(button(wrapper, 'Add')).toBeUndefined();
    });

    it('has no remove button on a row the policy would refuse', () => {
        const wrapper = mount(EventCredits, {
            props: { panel: panel({ credits: [{ id: 1, artist: 'Jo', department: 'production', role: 'Sound' }] }) },
        });

        expect(button(wrapper, 'Remove')).toBeUndefined();
    });

    it('copies from another run', async () => {
        axios.post.mockResolvedValueOnce({ data: panel() });
        const wrapper = mount(EventCredits, {
            props: { panel: panel({ can_copy: true, other_runs: [{ id: 9, name: 'Boston' }] }) },
        });

        await wrapper.findAll('select').at(-1).setValue(9);
        await button(wrapper, 'Copy').trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/hosting/event/ha-ha-ha/credits/copy', { from_event_id: 9 });
    });
});
