/**
 * Specs for how PageComponents/Creation/index.vue treats an event that has
 * left its edit window (Event::EDIT_WINDOW_DAYS — 90 days after the run ends).
 *
 * isEditLocked is the SERVER's answer, appended to the event JSON, and it
 * already exempts moderators and admins — so the dashboard never decides for
 * itself. What it does is get there first: instead of sending the organizer
 * into a request that would be refused, it explains that the record is kept
 * and offers the duplicate, which is the one route left open on purpose.
 */
import { mount } from '@vue/test-utils';
import axios from 'axios';
import HostingDashboard from '@/PageComponents/Creation/index.vue';

vi.mock('axios', () => ({
    default: { get: vi.fn(() => Promise.resolve({ data: {} })), post: vi.fn(), delete: vi.fn() },
}));

function makeEvent(overrides = {}) {
    return {
        id: 1,
        slug: 'the-locked-vault',
        name: 'The Locked Vault',
        status: 'p',
        closingDate: '2025-01-31 00:00:00',
        isShowing: false,
        isEditLocked: true,
        images: [],
        ...overrides,
    };
}

function mountDashboard({ event = makeEvent(), user = {} } = {}) {
    return mount(HostingDashboard, {
        props: {
            organizer: { id: 1, name: 'Test Org', slug: 'test-org', events: [event] },
            user: { id: 1, isAdmin: false, isModerator: false, hasCreatedOrganizers: true, ...user },
        },
        global: { stubs: { teleport: true } },
    });
}

const LOCK_HEADING = 'Trying to edit an event that is over 90 days old?';
const YEAR = new Date().getFullYear();

describe('editing an event that has left its 90-day window', () => {
    beforeEach(() => {
        delete window.location;
        window.location = { href: '', search: '', pathname: '/hosting/events' };
        axios.post.mockReset();
        axios.delete.mockReset();
    });

    it('shows the lock instead of opening the editor', async () => {
        const event = makeEvent();
        const wrapper = mountDashboard({ event });

        wrapper.vm.editEvent(event);
        await wrapper.vm.$nextTick();

        expect(window.location.href).toBe('');
        expect(wrapper.text()).toContain(LOCK_HEADING);
        expect(wrapper.text()).toContain('This helps us preserve the historical record of past events.');
        expect(wrapper.text()).toContain('support@everythingimmersive.com');
    });

    it('suggests the naming convention with this year', async () => {
        const event = makeEvent();
        const wrapper = mountDashboard({ event });

        wrapper.vm.editEvent(event);
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain(`“Event Name (${YEAR})”`);
    });

    it('leads with when the run ended, and shows the event\'s poster', async () => {
        const event = makeEvent({ closingDate: '2025-01-31 00:00:00', thumbImagePath: 'event-images/vault-thumb.webp' });
        const wrapper = mountDashboard({ event });

        wrapper.vm.editEvent(event);
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Ended Jan 31, 2025');
        expect(wrapper.find('img[alt="The Locked Vault"]').attributes('src')).toContain('event-images/vault-thumb.webp');
    });

    it('duplicates from the modal and opens the copy', async () => {
        axios.post.mockResolvedValue({ data: { event: { slug: 'the-locked-vault-copy' } } });
        const event = makeEvent();
        const wrapper = mountDashboard({ event });

        wrapper.vm.editEvent(event);
        await wrapper.vm.$nextTick();
        await wrapper.vm.duplicateLockedEvent();

        expect(axios.post).toHaveBeenCalledWith('/api/events/the-locked-vault/duplicate');
        expect(window.location.href).toBe('/hosting/event/the-locked-vault-copy/edit');
    });

    it('holds a delete behind the same explanation', async () => {
        // Deleting is the most final rewrite of the record there is, and the
        // server refuses it too; no confirm(), because there is nothing to
        // confirm.
        const confirmSpy = vi.spyOn(window, 'confirm').mockImplementation(() => true);
        const event = makeEvent();
        const wrapper = mountDashboard({ event });

        await wrapper.vm.confirmRemoveEvent(event);
        await wrapper.vm.$nextTick();

        expect(confirmSpy).not.toHaveBeenCalled();
        expect(axios.delete).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain(LOCK_HEADING);
        confirmSpy.mockRestore();
    });

    it('closes without leaving the modal behind', async () => {
        const event = makeEvent();
        const wrapper = mountDashboard({ event });

        wrapper.vm.editEvent(event);
        await wrapper.vm.$nextTick();
        wrapper.vm.closeLockedModal();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).not.toContain(LOCK_HEADING);
    });

    it('opens the editor when the server says the event is not locked', async () => {
        // A finished run inside the window: the server would accept the
        // edit, so the dashboard must not second-guess it from closingDate.
        const event = makeEvent({ isEditLocked: false });
        const wrapper = mountDashboard({ event });

        wrapper.vm.editEvent(event);
        await wrapper.vm.$nextTick();

        expect(window.location.href).toContain('/hosting/event/the-locked-vault/edit');
        expect(wrapper.text()).not.toContain(LOCK_HEADING);
    });

    it('opens the lock on arrival from a redirected Edit link', async () => {
        // /hosting/event/{slug}/edit sends a locked event here with ?locked=,
        // so the public event page's Edit button lands on the same modal.
        window.location.search = '?locked=the-locked-vault';
        const wrapper = mountDashboard({ event: makeEvent() });
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain(LOCK_HEADING);
        expect(wrapper.text()).toContain('Ended Jan 31, 2025');
    });

    it('still offers the duplicate when the event is not in this organizer\'s list', async () => {
        // The redirect can arrive for an event of another organizer the user
        // belongs to. Only the slug is needed to duplicate it.
        window.location.search = '?locked=someone-elses-run';
        axios.post.mockResolvedValue({ data: { event: { slug: 'someone-elses-run-copy' } } });
        const wrapper = mountDashboard();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain(LOCK_HEADING);
        expect(wrapper.text()).toContain(`Event Name (${YEAR})`);
        expect(wrapper.text()).toContain('Past event');

        await wrapper.vm.duplicateLockedEvent();

        expect(axios.post).toHaveBeenCalledWith('/api/events/someone-elses-run/duplicate');
        expect(window.location.href).toBe('/hosting/event/someone-elses-run-copy/edit');
    });

    it('drops ?locked= from the URL when the redirect-opened modal is closed', async () => {
        // So a reload, or a back-forward restore, does not reopen it.
        window.location.search = '?locked=the-locked-vault';
        const replaceState = vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});
        const wrapper = mountDashboard({ event: makeEvent() });
        await wrapper.vm.$nextTick();

        wrapper.vm.closeLockedModal();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).not.toContain(LOCK_HEADING);
        expect(replaceState).toHaveBeenCalledWith({}, document.title, '/hosting/events');
        replaceState.mockRestore();
    });

    it('leaves the URL alone when the modal was opened by a click', async () => {
        const replaceState = vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});
        const event = makeEvent();
        const wrapper = mountDashboard({ event });

        wrapper.vm.editEvent(event);
        await wrapper.vm.$nextTick();
        wrapper.vm.closeLockedModal();

        expect(replaceState).not.toHaveBeenCalled();
        replaceState.mockRestore();
    });

    it('encodes a slug that arrived in the query string before posting it', async () => {
        // ?locked= is arbitrary input; the server still authorizes the
        // duplicate, but the path must not be reshaped by what is in it.
        window.location.search = '?locked=' + encodeURIComponent('odd slug/../x?y');
        axios.post.mockResolvedValue({ data: { event: { slug: 'copy' } } });
        const wrapper = mountDashboard();
        await wrapper.vm.$nextTick();

        await wrapper.vm.duplicateLockedEvent();

        expect(axios.post).toHaveBeenCalledWith('/api/events/odd%20slug%2F..%2Fx%3Fy/duplicate');
    });
});
