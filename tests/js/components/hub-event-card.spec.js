/**
 * Specs for PageComponents/Profile/Components/hub-event-card.vue
 *
 * Covers:
 *  - emits 'remove-requested' / 'select' with the event payload.
 *  - image fallback chain: event.images[0] -> event.largeImagePath -> none.
 *  - isPast applies the opacity-50 class.
 *  - organizer block only renders when event.organizer is present.
 */
import { mount } from '@vue/test-utils';
import HubEventCard from '@/PageComponents/Profile/Components/hub-event-card.vue';

function makeEvent(overrides = {}) {
    return {
        id: 1,
        name: 'Test Event',
        images: [],
        largeImagePath: null,
        remaining: { type: 'available', label: '3 shows left' },
        dateRangeLabel: null,
        organizer: null,
        ...overrides,
    };
}

function mountCard(props = {}) {
    return mount(HubEventCard, {
        props: { event: makeEvent(), ...props },
    });
}

describe('hub-event-card.vue', () => {
    it('emits remove-requested with the event on the remove button click', async () => {
        const event = makeEvent();
        const wrapper = mountCard({ event });

        await wrapper.find('button[aria-label="Remove from Liked Events"]').trigger('click');

        expect(wrapper.emitted('remove-requested')).toBeTruthy();
        expect(wrapper.emitted('remove-requested')[0]).toEqual([event]);
    });

    it('emits select with the event when the card body is clicked', async () => {
        const event = makeEvent();
        const wrapper = mountCard({ event });

        // The card body is the second button (first is the remove button).
        await wrapper.findAll('button')[1].trigger('click');

        expect(wrapper.emitted('select')).toBeTruthy();
        expect(wrapper.emitted('select')[0]).toEqual([event]);
    });

    it('renders from event.images[0] when present, preferring it over largeImagePath', () => {
        const wrapper = mountCard({
            event: makeEvent({
                images: [{ large_image_path: 'event-images/foo/orig.webp' }],
                largeImagePath: 'event-images/bar/orig.webp',
            }),
        });

        const img = wrapper.find('img');
        expect(img.attributes('src')).toContain('event-images/foo/orig');
        expect(img.attributes('src')).not.toContain('event-images/bar');
    });

    it('falls back to largeImagePath when there are no images', () => {
        const wrapper = mountCard({
            event: makeEvent({ images: [], largeImagePath: 'event-images/bar/orig.webp' }),
        });

        expect(wrapper.find('img').attributes('src')).toContain('event-images/bar/orig');
    });

    it('renders no image when neither images nor largeImagePath is present', () => {
        const wrapper = mountCard({ event: makeEvent({ images: [], largeImagePath: null }) });

        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('applies opacity-50 when isPast is true, and not when false', () => {
        const past = mountCard({ isPast: true });
        const upcoming = mountCard({ isPast: false });

        expect(past.find('.group').classes()).toContain('opacity-50');
        expect(upcoming.find('.group').classes()).not.toContain('opacity-50');
    });

    it('renders the organizer name and initial-avatar fallback when organizer has no thumbnail', () => {
        const wrapper = mountCard({
            event: makeEvent({ organizer: { name: 'Acme Events', thumbImagePath: null } }),
        });

        expect(wrapper.text()).toContain('Acme Events');
        expect(wrapper.find('span.font-bold').text()).toBe('A');
    });

    it('renders no organizer block when the event has no organizer', () => {
        const wrapper = mountCard({ event: makeEvent({ organizer: null }) });

        // Only the "remaining/dateRangeLabel" paragraph should exist, not an organizer name row.
        expect(wrapper.findAll('.rounded-full.overflow-hidden')).toHaveLength(0);
    });

    it('uses dateRangeLabel over remaining.label when both are present', () => {
        const wrapper = mountCard({
            event: makeEvent({ dateRangeLabel: 'Jun 1 - Jun 30', remaining: { type: 'available', label: 'fallback label' } }),
        });

        expect(wrapper.text()).toContain('Jun 1 - Jun 30');
        expect(wrapper.text()).not.toContain('fallback label');
    });
});
