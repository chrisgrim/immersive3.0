/** The bottom bar's show run picker: pick one, pick another, or none. */
import { mount, flushPromises } from '@vue/test-utils';
import { reactive } from 'vue';
import axios from 'axios';
import ShowRunPicker from '@/PageComponents/Creation/Core/Components/show-run-picker.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }));

const choices = [
    { id: 1, name: '7 Paintings', slug: '7-paintings', image: 'project-images/7/a.webp' },
    { id: 2, name: 'Teddy\'s Diamonds', slug: 'teddys', image: null },
];

function mountWith(event, saveCurrentStep = null) {
    axios.get.mockResolvedValue({ data: { choices } });
    const state = reactive({ slug: 'draft', status: '2', project_id: null, link_request: null, ...event });
    const wrapper = mount(ShowRunPicker, { global: { provide: { event: state, ...(saveCurrentStep ? { saveCurrentStep } : {}) } } });
    return { wrapper, state };
}

describe('show-run-picker.vue', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows the show run the draft is joining, with its picture', async () => {
        const { wrapper } = mountWith({ link_request: { id: 9, show: '7 Paintings', project_id: 1, image: 'project-images/7/a.webp' } });
        await flushPromises();

        expect(wrapper.text()).toContain('Part of');
        expect(wrapper.text()).toContain('7 Paintings');
        expect(wrapper.find('img').attributes('src')).toContain('project-images/7/a.webp');
    });

    it('switches to another show run, and back to none', async () => {
        const { wrapper, state } = mountWith({ link_request: { id: 9, show: '7 Paintings', project_id: 1 } });
        await flushPromises();
        axios.post.mockResolvedValueOnce({ data: { link_request: { id: 10 }, filled: [] } });

        await wrapper.find('button[aria-haspopup]').trigger('click');
        await wrapper.findAll('[role="option"]').find((b) => b.text().includes('Teddy')).trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/hosting/event/draft/link-request', { project_id: 2 });
        expect(state.link_request.project_id).toBe(2);

        axios.delete.mockResolvedValueOnce({});
        await wrapper.find('button[aria-haspopup]').trigger('click');
        await wrapper.findAll('[role="option"]').find((b) => b.text().includes('Not part')).trigger('click');
        await flushPromises();

        expect(axios.delete).toHaveBeenCalledWith('/hosting/event/draft/link-request');
        expect(state.link_request).toBeNull();
        expect(wrapper.text()).toContain('Not part of one');
    });

    it('stays out of the way on a live listing', async () => {
        const { wrapper } = mountWith({ status: 'p' });
        await flushPromises();

        expect(wrapper.find('button').exists()).toBe(false);
        expect(axios.get).not.toHaveBeenCalled();
    });

    it('saves the step before joining, and only then reloads to show what was filled in', async () => {
        const order = [];
        const save = vi.fn(async () => { order.push('save'); return 'saved'; });
        axios.post.mockImplementationOnce(async () => { order.push('join'); return { data: { link_request: { id: 3 }, filled: ['description'] } }; });
        delete window.location;
        window.location = { reload: vi.fn(() => order.push('reload')) };
        const { wrapper } = mountWith({}, save);
        await flushPromises();

        await wrapper.find('button[aria-haspopup]').trigger('click');
        await wrapper.findAll('[role="option"]').find((b) => b.text().includes('7 Paintings')).trigger('click');
        await flushPromises();

        expect(order).toEqual(['save', 'join', 'reload']);
    });

    it('does not reload over a step it could not save', async () => {
        const save = vi.fn(async () => 'invalid');
        axios.post.mockResolvedValueOnce({ data: { link_request: { id: 3 }, filled: ['description'] } });
        delete window.location;
        window.location = { reload: vi.fn() };
        const { wrapper, state } = mountWith({}, save);
        await flushPromises();

        await wrapper.find('button[aria-haspopup]').trigger('click');
        await wrapper.findAll('[role="option"]').find((b) => b.text().includes('7 Paintings')).trigger('click');
        await flushPromises();

        expect(window.location.reload).not.toHaveBeenCalled();
        expect(state.link_request.project_id).toBe(1);
        expect(wrapper.text()).toContain('you will see them once this step is saved');
    });
});
