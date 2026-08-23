/**
 * Specs for PageComponents/AccountSettings/Pages/Notifications.vue
 *
 * Covers:
 *  - Fetch-on-mount loads counts from the notification-preferences counts
 *    endpoint and clears loading.
 *  - Load-error state + retry.
 *  - Counts render with correct singular/plural wording.
 *  - Clicking "Clear all notifications" POSTs to the clear-all endpoint,
 *    disables the button while in flight, and shows a confirmation.
 *  - A failed clear shows an error and doesn't touch the displayed counts.
 */
import { mount, flushPromises } from '@vue/test-utils';
import Notifications from '@/PageComponents/AccountSettings/Pages/Notifications.vue';

function mockGet(counts = { saved_events_count: 3, followed_organizers_count: 2 }) {
    window.axios.get.mockResolvedValue({ data: counts });
}

async function mountLoaded(counts) {
    mockGet(counts);
    const wrapper = mount(Notifications);
    await flushPromises();
    return wrapper;
}

describe('AccountSettings Notifications.vue', () => {
    it('fetches counts on mount and clears the loading state', async () => {
        mockGet();
        const wrapper = mount(Notifications);
        expect(wrapper.text()).toContain('Loading');

        await flushPromises();

        expect(window.axios.get).toHaveBeenCalledWith('/api/hub/notification-preferences/counts');
        expect(wrapper.text()).not.toContain('Loading');
    });

    it('shows a load error and a working retry button when the GET fails', async () => {
        window.axios.get.mockRejectedValueOnce(new Error('network down'));
        const wrapper = mount(Notifications);
        await flushPromises();

        expect(wrapper.text()).toContain('Could not load your notification settings.');

        mockGet();
        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.text()).not.toContain('Could not load');
        expect(window.axios.get).toHaveBeenCalledTimes(2);
    });

    it('renders the saved-events and followed-organizers counts', async () => {
        const wrapper = await mountLoaded({ saved_events_count: 5, followed_organizers_count: 1 });

        expect(wrapper.text()).toContain('notifications on');
        expect(wrapper.text()).toContain('1');
        expect(wrapper.text()).toContain('organizer');
        expect(wrapper.text()).toContain('5');
        expect(wrapper.text()).toContain('saved events');
    });

    it('links the counts to the callers Liked Events page', async () => {
        const wrapper = await mountLoaded();

        expect(wrapper.find('a').attributes('href')).toBe(`/users/${window.Laravel.user.id}/events`);
    });

    it('POSTs to the clear-all endpoint and shows a confirmation', async () => {
        const wrapper = await mountLoaded();
        window.axios.post.mockResolvedValue({ data: { saved_events_count: 0, followed_organizers_count: 0 } });

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(window.axios.post).toHaveBeenCalledWith('/api/hub/notification-preferences/clear-all');
        expect(wrapper.text()).toContain('cleared');
    });

    it('disables the button while a clear is in flight', async () => {
        const wrapper = await mountLoaded();
        let resolvePost;
        window.axios.post.mockReturnValue(new Promise((resolve) => { resolvePost = resolve; }));

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.find('button').element.disabled).toBe(true);
        expect(wrapper.text()).toContain('Clearing');

        resolvePost({ data: { saved_events_count: 0, followed_organizers_count: 0 } });
        await flushPromises();
        expect(wrapper.find('button').element.disabled).toBe(false);
    });

    it('shows an error and leaves the counts unchanged when clearing fails', async () => {
        const wrapper = await mountLoaded({ saved_events_count: 3, followed_organizers_count: 2 });
        window.axios.post.mockRejectedValue(new Error('boom'));

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Could not clear notifications');
        expect(wrapper.text()).toContain('3');
        expect(wrapper.text()).toContain('2');
    });
});
