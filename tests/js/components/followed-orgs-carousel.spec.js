/**
 * Specs for PageComponents/Profile/Components/followed-orgs-carousel.vue
 *
 * Covers:
 *  - renders nothing when organizers is empty.
 *  - renders each organizer's link/name, with an initial-letter fallback
 *    when there's no thumbnail.
 *  - left/right scroll-arrow visibility tracks real scroll position
 *    (scrollLeft/scrollWidth/clientWidth), not just organizers.length.
 *  - clicking an arrow calls scrollBy in the right direction.
 *
 * jsdom does not compute real layout, so scrollWidth/clientWidth are 0 by
 * default — tests that need "the carousel overflows" stub those DOM
 * measurement properties directly on the mounted scroll container.
 */
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import FollowedOrgsCarousel from '@/PageComponents/Profile/Components/followed-orgs-carousel.vue';

function makeOrganizers(count = 3) {
    return Array.from({ length: count }, (_, i) => ({
        id: i + 1,
        slug: `org-${i + 1}`,
        name: `Organizer ${i + 1}`,
        thumbImagePath: null,
    }));
}

function mountCarousel(props = {}) {
    return mount(FollowedOrgsCarousel, {
        props: { organizers: makeOrganizers(), ...props },
    });
}

function stubOverflow(el, { scrollLeft = 0, scrollWidth = 1000, clientWidth = 300 } = {}) {
    Object.defineProperty(el, 'scrollWidth', { value: scrollWidth, configurable: true });
    Object.defineProperty(el, 'clientWidth', { value: clientWidth, configurable: true });
    // scroll(direction) uses offsetWidth (not clientWidth) for the scroll-by amount.
    Object.defineProperty(el, 'offsetWidth', { value: clientWidth, configurable: true });
    Object.defineProperty(el, 'scrollLeft', { value: scrollLeft, configurable: true, writable: true });
}

describe('followed-orgs-carousel.vue', () => {
    it('renders nothing when organizers is empty', () => {
        const wrapper = mountCarousel({ organizers: [] });
        expect(wrapper.find('.relative').exists()).toBe(false);
    });

    it('renders a link per organizer with the correct href and name', () => {
        const wrapper = mountCarousel({ organizers: makeOrganizers(2) });
        const links = wrapper.findAll('a');
        expect(links).toHaveLength(2);
        expect(links[0].attributes('href')).toBe('/organizers/org-1');
        expect(links[0].text()).toContain('Organizer 1');
    });

    it('shows an uppercase initial-letter fallback when there is no thumbnail', () => {
        const wrapper = mountCarousel({ organizers: [{ id: 1, slug: 'acme', name: 'acme events', thumbImagePath: null }] });
        expect(wrapper.find('span.font-bold').text()).toBe('A');
        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('renders an image when thumbImagePath is present', () => {
        const wrapper = mountCarousel({
            organizers: [{ id: 1, slug: 'acme', name: 'Acme', thumbImagePath: 'organizer-images/acme/orig.webp' }],
        });
        expect(wrapper.find('img').attributes('src')).toContain('organizer-images/acme/orig');
    });

    describe('scroll-arrow visibility', () => {
        it('shows no arrows when the content does not overflow (scrollWidth <= clientWidth)', async () => {
            const wrapper = mountCarousel();
            const container = wrapper.find('.overflow-x-auto').element;
            stubOverflow(container, { scrollLeft: 0, scrollWidth: 300, clientWidth: 300 });
            container.dispatchEvent(new Event('scroll'));
            await nextTick();

            expect(wrapper.findAll('button')).toHaveLength(0);
        });

        it('shows only the right arrow when scrolled to the start of overflowing content', async () => {
            const wrapper = mountCarousel();
            const container = wrapper.find('.overflow-x-auto').element;
            stubOverflow(container, { scrollLeft: 0, scrollWidth: 1000, clientWidth: 300 });
            container.dispatchEvent(new Event('scroll'));
            await nextTick();

            expect(wrapper.findAll('button')).toHaveLength(1);
        });

        it('shows only the left arrow when scrolled to the end of overflowing content', async () => {
            const wrapper = mountCarousel();
            const container = wrapper.find('.overflow-x-auto').element;
            stubOverflow(container, { scrollLeft: 700, scrollWidth: 1000, clientWidth: 300 });
            container.dispatchEvent(new Event('scroll'));
            await nextTick();

            expect(wrapper.findAll('button')).toHaveLength(1);
        });

        it('shows both arrows when scrolled to the middle of overflowing content', async () => {
            const wrapper = mountCarousel();
            const container = wrapper.find('.overflow-x-auto').element;
            stubOverflow(container, { scrollLeft: 350, scrollWidth: 1000, clientWidth: 300 });
            container.dispatchEvent(new Event('scroll'));
            await nextTick();

            expect(wrapper.findAll('button')).toHaveLength(2);
        });
    });

    it('clicking the right arrow scrolls forward by the container width', async () => {
        const wrapper = mountCarousel();
        const container = wrapper.find('.overflow-x-auto').element;
        stubOverflow(container, { scrollLeft: 0, scrollWidth: 1000, clientWidth: 300 });
        container.scrollBy = vi.fn();
        container.dispatchEvent(new Event('scroll'));
        await nextTick();

        await wrapper.findAll('button')[0].trigger('click'); // right arrow (left is hidden at scrollLeft: 0)

        expect(container.scrollBy).toHaveBeenCalledWith({ left: 300, behavior: 'smooth' });
    });
});
