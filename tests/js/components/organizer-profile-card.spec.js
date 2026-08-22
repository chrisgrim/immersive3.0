/**
 * Specs for GlobalComponents/organizer-profile-card.vue
 *
 * Shared between Organizer/show.vue and hub-event-detail.vue's drill-down —
 * a bug here surfaces on both. Covers the component's actual computed
 * logic rather than static markup:
 *  - organizerImage's 3-way fallback (images[0].large_image_path ->
 *    thumbImagePath -> null/initial-letter placeholder).
 *  - calculateYearsOnEI's month/day-boundary adjustment (the classic
 *    off-by-one source for "years since a date" math) — computed relative
 *    to the real current date at test time, not a hardcoded date, so this
 *    doesn't rot into a flaky test next year.
 *  - The events-count fallback chain (events_count -> events?.length -> 0).
 */
import { mount } from '@vue/test-utils';
import OrganizerProfileCard from '@/GlobalComponents/organizer-profile-card.vue';

function baseOrganizer(overrides = {}) {
    return {
        name: 'Test Organizer',
        created_at: '2020-01-01',
        ...overrides,
    };
}

function mountCard(organizerOverrides = {}) {
    return mount(OrganizerProfileCard, {
        props: { organizer: baseOrganizer(organizerOverrides) },
    });
}

describe('organizer-profile-card.vue', () => {
    describe('organizerImage fallback', () => {
        it('uses images[0].large_image_path when present', () => {
            const wrapper = mountCard({
                images: [{ large_image_path: 'organizer-images/foo/orig.webp' }],
                thumbImagePath: 'organizer-images/foo/thumb.jpg',
            });
            const img = wrapper.find('img');
            expect(img.exists()).toBe(true);
            expect(img.attributes('src')).toContain('organizer-images/foo/orig.webp');
        });

        it('falls back to thumbImagePath when there are no images', () => {
            const wrapper = mountCard({ thumbImagePath: 'organizer-images/bar/thumb.jpg' });
            const img = wrapper.find('img');
            expect(img.exists()).toBe(true);
            expect(img.attributes('src')).toContain('organizer-images/bar/thumb.jpg');
        });

        it('falls back to thumbImagePath when images is an empty array', () => {
            const wrapper = mountCard({ images: [], thumbImagePath: 'organizer-images/baz/thumb.jpg' });
            expect(wrapper.find('img').attributes('src')).toContain('organizer-images/baz/thumb.jpg');
        });

        it('renders the initial-letter placeholder when neither image source is present', () => {
            const wrapper = mountCard({ name: 'Zebra Events' });
            expect(wrapper.find('img').exists()).toBe(false);
            expect(wrapper.find('picture').exists()).toBe(false);
            expect(wrapper.text()).toContain('Z');
        });

        it('renders "?" as the placeholder when the organizer has no name', () => {
            const wrapper = mountCard({ name: '' });
            expect(wrapper.text()).toContain('?');
        });
    });

    describe('calculateYearsOnEI', () => {
        function isoDateYearsAgo(years, dayOffset = 0) {
            const d = new Date();
            d.setFullYear(d.getFullYear() - years);
            d.setDate(d.getDate() + dayOffset);
            return d.toISOString().slice(0, 10);
        }

        it('shows the full elapsed years when the anniversary already passed this year', () => {
            // 3 years ago, 10 days earlier in the calendar than today -> anniversary has passed.
            const wrapper = mountCard({ created_at: isoDateYearsAgo(3, -10) });
            expect(wrapper.text()).toContain('3');
        });

        it('subtracts one year when the anniversary has not happened yet this year', () => {
            // 3 years ago, 10 days LATER in the calendar than today -> anniversary hasn't hit yet.
            const wrapper = mountCard({ created_at: isoDateYearsAgo(3, 10) });
            expect(wrapper.text()).toContain('2');
        });

        it('shows 0 for an organizer that joined today', () => {
            const wrapper = mountCard({ created_at: isoDateYearsAgo(0) });
            expect(wrapper.text()).toContain('Years on EI');
            // 0 years elapsed, joined exactly today.
            const statNumbers = wrapper.findAll('p.text-5xl');
            expect(statNumbers[1].text()).toBe('0');
        });
    });

    describe('events count', () => {
        it('uses organizer.events_count when present', () => {
            const wrapper = mountCard({ events_count: 12, events: [{}, {}] });
            const statNumbers = wrapper.findAll('p.text-5xl');
            expect(statNumbers[0].text()).toBe('12');
        });

        it('falls back to organizer.events.length when events_count is absent', () => {
            const wrapper = mountCard({ events: [{}, {}, {}] });
            const statNumbers = wrapper.findAll('p.text-5xl');
            expect(statNumbers[0].text()).toBe('3');
        });

        it('falls back to 0 when neither events_count nor events is present', () => {
            const wrapper = mountCard({});
            const statNumbers = wrapper.findAll('p.text-5xl');
            expect(statNumbers[0].text()).toBe('0');
        });
    });

    describe('organizer name', () => {
        it('renders the organizer name', () => {
            const wrapper = mountCard({ name: 'Mister & Mischief' });
            expect(wrapper.text()).toContain('Mister & Mischief');
        });
    });
});
