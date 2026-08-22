/**
 * Specs for PageComponents/AccountSettings/Pages/Notifications.vue
 *
 * Covers:
 *  - Fetch-on-mount populates preferences and clears loading.
 *  - Load-error state + retry.
 *  - The `checked` binding is the INVERSE of the stored preference value —
 *    this is exactly the class of bug that shipped earlier this session
 *    (the toggle showed the opposite of what the label said) and was only
 *    caught by live browser testing, not any automated test.
 *  - Toggling PATCHes the full two-key state with the clicked key flipped.
 *  - `saving` disables both toggles while a PATCH is in flight.
 *  - A rejected PATCH reverts the optimistic change and shows an error.
 */
import { mount, flushPromises } from '@vue/test-utils';
import Notifications from '@/PageComponents/AccountSettings/Pages/Notifications.vue';

function mockGet(prefs = { saved_event_new_dates: false, followed_organizer_new_event: false }) {
    window.axios.get.mockResolvedValue({ data: { notification_preferences: prefs } });
}

async function mountLoaded(prefs) {
    mockGet(prefs);
    const wrapper = mount(Notifications);
    await flushPromises();
    return wrapper;
}

describe('AccountSettings Notifications.vue', () => {
    it('fetches preferences on mount and clears the loading state', async () => {
        mockGet();
        const wrapper = mount(Notifications);
        expect(wrapper.text()).toContain('Loading');

        await flushPromises();

        expect(window.axios.get).toHaveBeenCalledWith('/api/hub/notification-preferences');
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

    it('shows both toggles as ON when the preference is false (mail is off, so "Turn off X" is true)', async () => {
        const wrapper = await mountLoaded({ saved_event_new_dates: false, followed_organizer_new_event: false });

        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        expect(checkboxes).toHaveLength(2);
        expect(checkboxes[0].element.checked).toBe(true);
        expect(checkboxes[1].element.checked).toBe(true);
    });

    it('shows a toggle as OFF when the preference is true (mail is on, so "Turn off X" is false)', async () => {
        const wrapper = await mountLoaded({ saved_event_new_dates: true, followed_organizer_new_event: false });

        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        expect(checkboxes[0].element.checked).toBe(false);
        expect(checkboxes[1].element.checked).toBe(true);
    });

    it('PATCHes the full two-key state with the clicked key flipped', async () => {
        const wrapper = await mountLoaded({ saved_event_new_dates: false, followed_organizer_new_event: false });
        window.axios.patch.mockResolvedValue({ data: {} });

        await wrapper.findAll('input[type="checkbox"]')[0].trigger('change');
        await flushPromises();

        expect(window.axios.patch).toHaveBeenCalledWith('/api/hub/notification-preferences', {
            saved_event_new_dates: true,
            followed_organizer_new_event: false,
        });
    });

    it('disables both toggles while a save is in flight', async () => {
        const wrapper = await mountLoaded();
        let resolvePatch;
        window.axios.patch.mockReturnValue(new Promise((resolve) => { resolvePatch = resolve; }));

        await wrapper.findAll('input[type="checkbox"]')[0].trigger('change');
        await flushPromises();

        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        expect(checkboxes[0].element.disabled).toBe(true);
        expect(checkboxes[1].element.disabled).toBe(true);

        resolvePatch({ data: {} });
        await flushPromises();
        expect(wrapper.findAll('input[type="checkbox"]')[0].element.disabled).toBe(false);
    });

    it('reverts the optimistic toggle and shows an error when the PATCH fails', async () => {
        const wrapper = await mountLoaded({ saved_event_new_dates: false, followed_organizer_new_event: false });
        window.axios.patch.mockRejectedValue(new Error('boom'));

        await wrapper.findAll('input[type="checkbox"]')[0].trigger('change');
        await flushPromises();

        // Reverted back to checked=true (preference still false).
        expect(wrapper.findAll('input[type="checkbox"]')[0].element.checked).toBe(true);
        expect(wrapper.text()).toContain('Could not save that change');
    });
});
