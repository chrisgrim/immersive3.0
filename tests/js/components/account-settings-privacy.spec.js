/**
 * Specs for PageComponents/AccountSettings/Pages/Privacy.vue
 *
 * Covers:
 *  - Fetch-on-mount populates the two visibility toggles (checked = raw
 *    value here, NOT inverted — unlike Notifications.vue, this component's
 *    label reads as a state ("Show X"), not an action ("Turn off X")).
 *  - Toggling PATCHes both keys, reverts + shows an error on failure.
 *  - "Request my personal data" button state machine.
 *  - Delete-account modal: the confirm button only enables once the typed
 *    text matches the user's own email (case-insensitively) — window.Laravel
 *    .user.email is 't@e.com' per tests/js/setup.js.
 *  - A 422 delete response shows the server's blocking message instead of
 *    the generic one.
 */
import { mount, flushPromises } from '@vue/test-utils';
import Privacy from '@/PageComponents/AccountSettings/Pages/Privacy.vue';

function mockGet(prefs = { followed_organizers: false, saved_events_count: false }) {
    window.axios.get.mockResolvedValue({ data: { privacy_preferences: prefs } });
}

async function mountLoaded(prefs) {
    mockGet(prefs);
    // Stub Teleport to render its content in place — the delete-confirmation
    // modal uses <Teleport to="body">, and without this, VTU's find()/
    // findAll() can't see teleported content since it renders outside the
    // wrapper's own DOM subtree (attachTo: document.body hits an
    // app.onUnmount incompatibility with the installed VTU/Vue versions).
    const wrapper = mount(Privacy, { global: { stubs: { teleport: true } } });
    await flushPromises();
    return wrapper;
}

describe('AccountSettings Privacy.vue', () => {
    it('fetches preferences on mount and reflects them directly (not inverted) on the toggles', async () => {
        const wrapper = await mountLoaded({ followed_organizers: true, saved_events_count: false });

        expect(window.axios.get).toHaveBeenCalledWith('/api/account-settings/privacy');
        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        expect(checkboxes[0].element.checked).toBe(true);
        expect(checkboxes[1].element.checked).toBe(false);
    });

    it('shows a load error and a working retry button when the GET fails', async () => {
        window.axios.get.mockRejectedValueOnce(new Error('down'));
        const wrapper = mount(Privacy);
        await flushPromises();
        expect(wrapper.text()).toContain('Could not load your privacy settings.');

        mockGet();
        await wrapper.find('button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).not.toContain('Could not load');
    });

    it('PATCHes both keys with the clicked one flipped', async () => {
        const wrapper = await mountLoaded({ followed_organizers: false, saved_events_count: true });
        window.axios.patch.mockResolvedValue({ data: {} });

        await wrapper.findAll('input[type="checkbox"]')[0].trigger('change');
        await flushPromises();

        expect(window.axios.patch).toHaveBeenCalledWith('/api/account-settings/privacy', {
            followed_organizers: true,
            saved_events_count: true,
        });
    });

    it('reverts the optimistic toggle and shows an error when the PATCH fails', async () => {
        const wrapper = await mountLoaded({ followed_organizers: false, saved_events_count: false });
        window.axios.patch.mockRejectedValue(new Error('boom'));

        await wrapper.findAll('input[type="checkbox"]')[0].trigger('change');
        await flushPromises();

        expect(wrapper.findAll('input[type="checkbox"]')[0].element.checked).toBe(false);
        expect(wrapper.text()).toContain('Could not save that change');
    });

    it('walks the request-data button through Sending… to Request sent, then stays disabled', async () => {
        const wrapper = await mountLoaded();
        let resolvePost;
        window.axios.post.mockReturnValue(new Promise((resolve) => { resolvePost = resolve; }));

        const button = wrapper.findAll('button').find((b) => b.text().includes('Request data'));
        await button.trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Sending…');

        resolvePost({ data: {} });
        await flushPromises();
        expect(wrapper.text()).toContain('Request sent');
        expect(window.axios.post).toHaveBeenCalledWith('/api/account-settings/privacy/data-request');

        const sentButton = wrapper.findAll('button').find((b) => b.text().includes('Request sent'));
        expect(sentButton.element.disabled).toBe(true);
    });

    describe('delete account modal', () => {
        async function openModal(wrapper) {
            const deleteButton = wrapper.findAll('button').find((b) => b.text() === 'Delete my account');
            await deleteButton.trigger('click');
        }

        it('keeps the confirm button disabled until the typed text matches the users own email', async () => {
            const wrapper = await mountLoaded();
            await openModal(wrapper);
            // Re-queried after every state change rather than reused — Vue can
            // patch in a way that leaves a cached DOMWrapper's element stale.
            const findConfirmButton = () => wrapper.findAll('button').find((b) => b.text().includes('Delete my account') && b.attributes('type') === 'button' && b.classes().includes('bg-red-600'));

            expect(findConfirmButton().element.disabled).toBe(true);

            await wrapper.find('input[type="text"]').setValue('not-the-email');
            expect(findConfirmButton().element.disabled).toBe(true);

            await wrapper.find('input[type="text"]').setValue('T@E.COM'); // case-insensitive match
            expect(findConfirmButton().element.disabled).toBe(false);
        });

        it('shows the servers blocking message on a 422 instead of deleting', async () => {
            const wrapper = await mountLoaded();
            await openModal(wrapper);
            await wrapper.find('input[type="text"]').setValue('t@e.com');

            window.axios.delete.mockRejectedValue({
                response: { status: 422, data: { message: 'This account still owns an organizer page.' } },
            });

            const confirmButton = wrapper.findAll('button').find((b) => b.text().includes('Delete my account') && b.classes().includes('bg-red-600'));
            await confirmButton.trigger('click');
            await flushPromises();

            expect(wrapper.text()).toContain('This account still owns an organizer page.');
        });
    });
});
