/**
 * Specs for the past-event edit lock in PageComponents/Creation/index.vue
 *
 * An organizer clicking "Edit Event" on a finished run used to be sent to
 * /hosting/event/{slug}/edit, where HostEventController::assertEditable()
 * answered with a bare 403 "Access Denied" page. The refusal was correct;
 * the presentation told them they'd done something wrong rather than that
 * their show had ended.
 *
 * These lock the client-side predicate to the server's, since the two
 * disagreeing is the only way this can go wrong: too strict and an
 * organizer can't edit a live event, too loose and they're back on the 403.
 */
import { mount } from '@vue/test-utils';
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
        closingDate: '2026-01-31 00:00:00',
        isShowing: false,
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

// The dashboard opens the edit flow through editEvent(); going through the
// component's own handler is the point, since that is what the button calls.
function clickEdit(wrapper, event) {
    wrapper.vm.editEvent(event);
    return wrapper.vm.$nextTick();
}

describe('the past-event edit lock', () => {
    beforeEach(() => {
        delete window.location;
        window.location = { href: '' };
    });

    it('stops an organizer navigating into the 403 and explains instead', async () => {
        const event = makeEvent();
        const wrapper = mountDashboard({ event });

        await clickEdit(wrapper, event);

        expect(window.location.href).toBe('');
        expect(wrapper.text()).toContain('has finished its run');
        expect(wrapper.text()).toContain('The Locked Vault');
    });

    it('offers the action the organizer actually wanted', async () => {
        const event = makeEvent();
        const wrapper = mountDashboard({ event });

        await clickEdit(wrapper, event);

        expect(wrapper.text()).toContain('Start a new listing');
    });

    it('lets a still-running event through to the editor', async () => {
        const event = makeEvent({ isShowing: true });
        const wrapper = mountDashboard({ event });

        await clickEdit(wrapper, event);

        expect(window.location.href).toContain('/hosting/event/the-locked-vault/edit');
        expect(wrapper.text()).not.toContain('has finished its run');
    });

    it('lets a draft with no schedule yet through', async () => {
        // A null closingDate is NOT "already happened" — it is the state every
        // event starts in, before the dates step. assertEditable() carries the
        // same carve-out, and without it a brand-new draft would be uneditable.
        const event = makeEvent({ status: '3', closingDate: null, isShowing: false });
        const wrapper = mountDashboard({ event });

        await clickEdit(wrapper, event);

        expect(window.location.href).toContain('/hosting/event/the-locked-vault/edit');
        expect(wrapper.text()).not.toContain('has finished its run');
    });

    it('exempts moderators, who edit past events on purpose', async () => {
        // assertEditable() exempts them for historical corrections; blocking
        // them here would contradict a server that would have allowed it.
        const event = makeEvent();
        const wrapper = mountDashboard({ event, user: { isModerator: true } });

        await clickEdit(wrapper, event);

        expect(window.location.href).toContain('/hosting/event/the-locked-vault/edit');
        expect(wrapper.text()).not.toContain('has finished its run');
    });

    it('still blocks a finished event that was rejected rather than published', async () => {
        // The dashboard's own isPastEvent() only counts status 'p' because it
        // groups the visible list. The server's rule ignores status entirely,
        // so a rejected event whose dates passed is refused too.
        const event = makeEvent({ status: 'n' });
        const wrapper = mountDashboard({ event });

        await clickEdit(wrapper, event);

        expect(window.location.href).toBe('');
        expect(wrapper.text()).toContain('has finished its run');
    });

    it('leaves the under-review lock alone', async () => {
        // Different rule, different message — an event in review is not over,
        // it is waiting, and it becomes editable again on a decision.
        const event = makeEvent({ status: 'r', isShowing: true });
        const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {});
        const wrapper = mountDashboard({ event });

        await clickEdit(wrapper, event);

        expect(alertSpy).toHaveBeenCalledWith(expect.stringContaining('under review'));
        expect(wrapper.text()).not.toContain('has finished its run');
        alertSpy.mockRestore();
    });
});
