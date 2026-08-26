/**
 * Specs for PageComponents/EventShow/event-actions.vue
 *
 * The product rule these lock in: hearting an event ONLY hearts it. It does
 * not subscribe anyone to email and does not follow the organizer. The
 * "Show has been liked!" popup's toggle is the only thing that turns email
 * on, and it starts off.
 *
 * That rule lives in two places that must agree — this switch's initial
 * state, and the server reading a null notify_new_dates as "do not email"
 * (FavoriteController::mapEvent, SavedEventNewDatesNotification::via()).
 * When they disagreed, the switch read off while the user was already
 * subscribed, and the single click meant to opt OUT sent enabled:true —
 * subscribing them harder and following the organizer as well.
 */
import { mount, flushPromises } from '@vue/test-utils';
import EventActions from '@/PageComponents/EventShow/event-actions.vue';

function makeEvent(overrides = {}) {
    return {
        slug: 'test-event',
        name: 'Test Event',
        isFavorited: false,
        organizer: { name: 'Acme Events', slug: 'acme' },
        ...overrides,
    };
}

function mountActions(props = {}) {
    return mount(EventActions, {
        props: { event: makeEvent(), user: { id: 1 }, ...props },
        global: { directives: { 'click-outside': {} } },
    });
}

async function heartTheEvent(wrapper) {
    window.axios.post.mockResolvedValueOnce({ data: { isFavorited: true } });
    await wrapper.findAll('button')[1].trigger('click');
    await flushPromises();
}

describe('event-actions.vue', () => {
    describe('hearting an event', () => {
        it('saves it without subscribing to anything', async () => {
            const wrapper = mountActions();
            await heartTheEvent(wrapper);

            // One call, the favorite itself. No notify PATCH rides along.
            expect(window.axios.post).toHaveBeenCalledWith('/api/events/test-event/favorite');
            expect(window.axios.patch).not.toHaveBeenCalled();
        });

        it('opens the get-updates toggle OFF', async () => {
            const wrapper = mountActions();
            await heartTheEvent(wrapper);

            expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(false);
        });
    });

    describe('the get-updates toggle', () => {
        it('is what opts the user in, and only when they click it', async () => {
            const wrapper = mountActions({ event: makeEvent({ slug: 'my-event' }) });
            await heartTheEvent(wrapper);

            window.axios.patch.mockResolvedValueOnce({ data: {} });
            await wrapper.find('input[type="checkbox"]').trigger('change');
            await flushPromises();

            expect(window.axios.patch).toHaveBeenCalledWith(
                '/api/hub/events/my-event/notify-updates',
                { enabled: true },
            );
        });

        it('reverts if the opt-in request fails, so it never lies about being on', async () => {
            const wrapper = mountActions();
            await heartTheEvent(wrapper);
            vi.spyOn(console, 'error').mockImplementation(() => {});

            window.axios.patch.mockRejectedValueOnce(new Error('network'));
            await wrapper.find('input[type="checkbox"]').trigger('change');
            await flushPromises();

            expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(false);
        });
    });
});
