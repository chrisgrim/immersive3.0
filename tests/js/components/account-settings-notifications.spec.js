/**
 * Account Settings > Notifications: sections of email switches (only the ones
 * the server sends are shown), saved on each toggle and put back on failure,
 * the "turn them all off" for per-item follows, and the always-on list.
 */
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import Notifications from '@/PageComponents/AccountSettings/Pages/Notifications.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), patch: vi.fn(), post: vi.fn() } }));

const memberSettings = { messages: true, saved_event_dates: true, followed_organizer_events: false };

function mockLoad(settings = memberSettings, counts = { saved_events_count: 3, followed_organizers_count: 1 }) {
    axios.get.mockImplementation((url) => Promise.resolve({ data: url.includes('counts') ? counts : { settings } }));
}

async function mountLoaded(settings) {
    mockLoad(settings);
    const wrapper = mount(Notifications);
    await flushPromises();
    return wrapper;
}

const switchFor = (wrapper, key) => wrapper.find(`[data-notification-switch="${key}"]`);

describe('Account Settings notifications', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        window.Laravel = { user: { id: 7 } };
    });

    it('shows only the switches the server sends, each as it is', async () => {
        const wrapper = await mountLoaded();
        expect(switchFor(wrapper, 'messages').attributes('aria-checked')).toBe('true');
        expect(switchFor(wrapper, 'followed_organizer_events').attributes('aria-checked')).toBe('false');
        expect(switchFor(wrapper, 'events').exists()).toBe(false);
        expect(wrapper.find('[data-notification-section="admin"]').exists()).toBe(false);
        expect(wrapper.find('[data-notification-section="always"]').text()).toContain('Always on');
    });

    it('admins see the review emails', async () => {
        const wrapper = await mountLoaded({ ...memberSettings, events: true, organizers: false, claims: false });
        expect(wrapper.find('[data-notification-section="admin"]').text()).toContain('New events to review');
        expect(switchFor(wrapper, 'organizers').attributes('aria-checked')).toBe('false');
    });

    it('saves a switch at once', async () => {
        const wrapper = await mountLoaded();
        axios.patch.mockResolvedValueOnce({ data: { settings: { ...memberSettings, messages: false } } });

        await switchFor(wrapper, 'messages').trigger('click');
        await flushPromises();

        expect(axios.patch).toHaveBeenCalledWith('/api/account/notification-settings', { messages: false });
        expect(switchFor(wrapper, 'messages').attributes('aria-checked')).toBe('false');
    });

    it('puts a switch back and says so when saving fails', async () => {
        const wrapper = await mountLoaded();
        axios.patch.mockRejectedValueOnce(new Error('down'));

        await switchFor(wrapper, 'saved_event_dates').trigger('click');
        await flushPromises();

        expect(switchFor(wrapper, 'saved_event_dates').attributes('aria-checked')).toBe('true');
        expect(wrapper.find('[data-notification-row="saved_event_dates"]').text()).toContain('did not save');
    });

    it('turns every per-item follow email off at once', async () => {
        const wrapper = await mountLoaded();
        expect(wrapper.find('[data-notification-clear-all]').text()).toContain('3');
        axios.post.mockResolvedValueOnce({ data: { saved_events_count: 0, followed_organizers_count: 0 } });

        await wrapper.find('[data-notification-clear-all] button').trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/hub/notification-preferences/clear-all');
        expect(wrapper.find('[data-notification-clear-all]').text()).toContain('turned off');
    });

    it('shows a retry when loading fails', async () => {
        axios.get.mockRejectedValue(new Error('down'));
        const wrapper = mount(Notifications);
        await flushPromises();
        expect(wrapper.text()).toContain('Could not load');
    });
});
