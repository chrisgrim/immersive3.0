/**
 * Specs for how PageComponents/Creation/index.vue treats a finished run.
 *
 * A finished event used to be refused outright — the server 403'd anyone but a
 * moderator, and this dashboard put a modal in front of the Edit button
 * explaining why. That blocked the single most ordinary reason an organizer
 * comes back to a finished event: they are running the show again and want to
 * add dates. Their only route was duplicating, which starts a new listing at a
 * new URL and abandons the original's favourites and click stats.
 *
 * A finished run is editable now, for 90 days (Event::EDIT_WINDOW_DAYS). What
 * protects the record meanwhile is narrower and lives in Show::saveShows(),
 * which refuses to DELETE an already-passed show for a non-moderator on every
 * write path — history can be added to, not erased. So inside that window
 * there is nothing for this dashboard to refuse; these lock in that it
 * doesn't, and that it opens where the organizer actually needs to be. What
 * happens once the window has passed is hosting-locked-event.spec.js.
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

function clickEdit(wrapper, event) {
    wrapper.vm.editEvent(event);
    return wrapper.vm.$nextTick();
}

describe('editing a finished event', () => {
    beforeEach(() => {
        delete window.location;
        window.location = { href: '' };
    });

    it('opens the event instead of refusing', async () => {
        const event = makeEvent();
        const wrapper = mountDashboard({ event });

        await clickEdit(wrapper, event);

        expect(window.location.href).toContain('/hosting/event/the-locked-vault/edit');
        expect(wrapper.text()).not.toContain('has finished its run');
    });

    it('lands on Dates, which is why they came back', async () => {
        // Adding dates revives the listing at its own URL. Opening on
        // "What's your event called?" buries the one step that matters.
        const event = makeEvent();
        const wrapper = mountDashboard({ event });

        await clickEdit(wrapper, event);

        expect(window.location.href).toContain('view=Dates');
    });

    it('does not send a still-running event to Dates', async () => {
        const event = makeEvent({ isShowing: true });
        const wrapper = mountDashboard({ event });

        await clickEdit(wrapper, event);

        expect(window.location.href).toContain('/hosting/event/the-locked-vault/edit');
        expect(window.location.href).not.toContain('view=Dates');
    });

    it('treats a moderator no differently, now that nobody is blocked', async () => {
        const event = makeEvent();
        const wrapper = mountDashboard({ event, user: { isModerator: true } });

        await clickEdit(wrapper, event);

        expect(window.location.href).toContain('/hosting/event/the-locked-vault/edit');
    });

    it('still holds an event that is under review', async () => {
        // A different rule entirely: an event in review is not over, it is
        // waiting, and it becomes editable again on a decision.
        const event = makeEvent({ status: 'r', isShowing: true });
        const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {});
        const wrapper = mountDashboard({ event });

        await clickEdit(wrapper, event);

        expect(alertSpy).toHaveBeenCalledWith(expect.stringContaining('under review'));
        expect(window.location.href).toBe('');
        alertSpy.mockRestore();
    });
});
