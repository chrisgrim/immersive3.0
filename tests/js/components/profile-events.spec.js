/**
 * Specs for PageComponents/Profile/Pages/Events.vue
 *
 * Covers:
 *  - Loading / empty / list states.
 *  - live vs. past grouping (remaining.type === 'ended') and the past
 *    section's collapsed-by-default toggle.
 *  - Pagination only renders when total > per_page (delegated to
 *    pagination.vue's own @paginate emit, re-emitted verbatim).
 *  - select/remove-requested re-emission from hub-event-card, and
 *    notify-updated re-emission from hub-event-detail when selectedEvent is set.
 */
import { mount } from '@vue/test-utils';
import Events from '@/PageComponents/Profile/Pages/Events.vue';
import HubEventCard from '@/PageComponents/Profile/Components/hub-event-card.vue';
import HubEventDetail from '@/PageComponents/Profile/Components/hub-event-detail.vue';

function makeEvent(overrides = {}) {
    return {
        id: 1,
        slug: 'my-event',
        name: 'My Event',
        category: null,
        organizer: null,
        images: [],
        largeImagePath: null,
        price_range: null,
        remaining: { type: 'live', label: 'Ongoing' },
        dateRangeLabel: null,
        runDateParts: null,
        hasLocation: true,
        location_latlon: null,
        location: null,
        remotelocations: [],
        notifyUpdates: true,
        ...overrides,
    };
}

function pagination({ current_page = 1, last_page = 1, per_page = 24, total = 0 } = {}) {
    return { current_page, last_page, per_page, total };
}

function mountEvents(props = {}) {
    return mount(Events, {
        props: {
            events: { data: [], ...pagination() },
            loading: false,
            selectedEvent: null,
            ...props,
        },
    });
}

describe('Profile/Pages/Events.vue', () => {
    describe('states', () => {
        it('shows a loading message while loading', () => {
            const wrapper = mountEvents({ loading: true, events: { data: [], ...pagination() } });
            expect(wrapper.text()).toContain('Loading');
        });

        it('shows an empty state when there are no saved events', () => {
            const wrapper = mountEvents({ events: { data: [], ...pagination() } });
            expect(wrapper.text()).toContain('Nothing saved yet');
        });

        it('renders the detail pane instead of the list when selectedEvent is set', () => {
            const event = makeEvent();
            const wrapper = mountEvents({
                events: { data: [event], ...pagination({ total: 1 }) },
                selectedEvent: event,
            });
            expect(wrapper.findComponent(HubEventDetail).exists()).toBe(true);
            expect(wrapper.findComponent(HubEventCard).exists()).toBe(false);
        });
    });

    describe('live vs. past grouping', () => {
        it('renders live events in the main list and excludes past ones', () => {
            const live = makeEvent({ id: 1, name: 'Live Event', remaining: { type: 'live' } });
            const past = makeEvent({ id: 2, name: 'Past Event', remaining: { type: 'ended' } });
            const wrapper = mountEvents({ events: { data: [live, past], ...pagination({ total: 2 }) } });

            const cards = wrapper.findAllComponents(HubEventCard);
            expect(cards).toHaveLength(1);
            expect(cards[0].props('event').id).toBe(1);
        });

        it('starts with the past-events section collapsed, and toggles it open on click', async () => {
            const past = makeEvent({ id: 2, remaining: { type: 'ended' } });
            const wrapper = mountEvents({ events: { data: [past], ...pagination({ total: 1 }) } });

            const toggle = wrapper.find('button[aria-expanded]');
            expect(toggle.attributes('aria-expanded')).toBe('false');
            // Only the live-list HubEventCard renders while collapsed (none here, since this fixture is all-past).
            expect(wrapper.findAllComponents(HubEventCard)).toHaveLength(0);

            await toggle.trigger('click');

            expect(toggle.attributes('aria-expanded')).toBe('true');
            expect(wrapper.findAllComponents(HubEventCard)).toHaveLength(1);
            expect(wrapper.findComponent(HubEventCard).props('isPast')).toBe(true);
        });

        it('shows a divider between live and past sections only when both exist', () => {
            const live = makeEvent({ id: 1, remaining: { type: 'live' } });
            const withBoth = mountEvents({
                events: { data: [live, makeEvent({ id: 2, remaining: { type: 'ended' } })], ...pagination({ total: 2 }) },
            });
            expect(withBoth.find('.border-t').exists()).toBe(true);

            const liveOnly = mountEvents({ events: { data: [live], ...pagination({ total: 1 }) } });
            expect(liveOnly.find('.border-t').exists()).toBe(false);
        });
    });

    describe('pagination', () => {
        it('renders pagination only when total exceeds per_page', () => {
            const event = makeEvent();
            const withMore = mountEvents({ events: { data: [event], ...pagination({ total: 50, per_page: 24 }) } });
            expect(withMore.findComponent({ name: 'pagination' }).exists() || withMore.find('ul').exists()).toBe(true);

            const withoutMore = mountEvents({ events: { data: [event], ...pagination({ total: 1, per_page: 24 }) } });
            expect(withoutMore.find('ul').exists()).toBe(false);
        });

        it('re-emits paginate from the pagination component', async () => {
            const event = makeEvent();
            const wrapper = mountEvents({ events: { data: [event], ...pagination({ total: 50, per_page: 24, last_page: 3 }) } });

            const pageLinks = wrapper.findAll('ul a, ul button');
            // Click whatever advances the page — pagination.vue's own behavior is
            // covered elsewhere; here we only care that Events.vue forwards it.
            const nextLink = pageLinks.find((l) => l.text().trim() === '2') ?? pageLinks[pageLinks.length - 1];
            await nextLink.trigger('click');

            expect(wrapper.emitted('paginate')).toBeTruthy();
        });
    });

    describe('re-emitted events from hub-event-card', () => {
        it('re-emits select with the clicked event', async () => {
            const event = makeEvent();
            const wrapper = mountEvents({ events: { data: [event], ...pagination({ total: 1 }) } });

            await wrapper.findComponent(HubEventCard).vm.$emit('select', event);

            expect(wrapper.emitted('select')).toBeTruthy();
            expect(wrapper.emitted('select')[0]).toEqual([event]);
        });

        it('re-emits remove-requested', async () => {
            const event = makeEvent();
            const wrapper = mountEvents({ events: { data: [event], ...pagination({ total: 1 }) } });

            await wrapper.findComponent(HubEventCard).vm.$emit('remove-requested', event);

            expect(wrapper.emitted('remove-requested')).toBeTruthy();
        });
    });

    describe('re-emitted events from hub-event-detail', () => {
        it('re-emits notify-updated when a selected event notifies an update', async () => {
            const event = makeEvent();
            const wrapper = mountEvents({
                events: { data: [event], ...pagination({ total: 1 }) },
                selectedEvent: event,
            });

            await wrapper.findComponent(HubEventDetail).vm.$emit('notify-updated', { enabled: false });

            expect(wrapper.emitted('notify-updated')).toBeTruthy();
            expect(wrapper.emitted('notify-updated')[0]).toEqual([{ enabled: false }]);
        });
    });
});
