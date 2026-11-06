/** The admin Venues screen: a picture goes up through its own authorized route. */
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import Venues from '@/PageComponents/Admin/Management/Venues.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() } }));

describe('Venues.vue', () => {
    it('uploads a picture to the venue being edited', async () => {
        axios.get.mockResolvedValue({ data: { data: [{ id: 1, slug: 'the-hall', name: 'The Hall', status: 'approved' }], current_page: 1, last_page: 1 } });
        axios.post.mockResolvedValue({ data: { venue: { largeImagePath: 'venue-images/the-hall/new.webp' } } });
        const wrapper = mount(Venues);
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text() === 'Edit').trigger('click');

        const input = wrapper.find('[data-venue-image]');
        Object.defineProperty(input.element, 'files', { value: [new File(['x'], 'a.jpg', { type: 'image/jpeg' })] });
        await input.trigger('change');
        await flushPromises();

        const [url, body] = axios.post.mock.calls[0];
        expect(url).toBe('/api/admin/venues/the-hall/image');
        expect(body.get('image').name).toBe('a.jpg');
    });

    it('keeps the editor open, with unsaved edits, after a picture goes up', async () => {
        axios.get.mockResolvedValue({ data: { data: [{ id: 1, slug: 'the-hall', name: 'The Hall', status: 'approved' }], current_page: 1, last_page: 1 } });
        axios.post.mockResolvedValue({ data: { venue: { largeImagePath: 'venue-images/the-hall/new.webp' } } });
        const wrapper = mount(Venues);
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text() === 'Edit').trigger('click');
        const directions = wrapper.findAll('[data-venue-editor] textarea').at(-1);
        await directions.setValue('Park on 5th, not saved yet');

        const input = wrapper.find('[data-venue-image]');
        Object.defineProperty(input.element, 'files', { value: [new File(['x'], 'a.jpg', { type: 'image/jpeg' })] });
        await input.trigger('change');
        await flushPromises();

        expect(wrapper.find('[data-venue-editor]').exists()).toBe(true);
        expect(wrapper.findAll('[data-venue-editor] textarea').at(-1).element.value).toBe('Park on 5th, not saved yet');
        expect(wrapper.text()).toContain('Replaces the current picture.');
    });
});
