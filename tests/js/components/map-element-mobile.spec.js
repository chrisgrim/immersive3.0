/**
 * Specs for PageComponents/Search/Components/map-element-mobile.vue — the
 * card that opens when a pin is tapped on the mobile map.
 *
 * The thumbnail is meant to be a fixed 3:4 box. It used to stretch to the
 * height of the text beside it (a flex row's default alignment), so a long
 * tagline turned it into a tall sliver. These pin the two things that keep
 * the card compact: the image box opts out of stretching, and the tagline
 * is clamped like the name is.
 */
import { mount } from '@vue/test-utils';
import { vi } from 'vitest';
import MapElementMobile from '@/PageComponents/Search/Components/map-element-mobile.vue';

// The component sniffs WebP support through a canvas, which jsdom does not
// implement.
beforeEach(() => {
    vi.spyOn(HTMLCanvasElement.prototype, 'toDataURL').mockReturnValue('data:image/webp;base64,');
});

const event = {
    slug: 'the-circuit',
    name: 'The Circuit: An Immersive Silent Disco Ballet',
    tag_line: 'A fully immersive silent disco ballet fusing theatre, dance, and nightlife into a provocative journey through the streets of Brooklyn.',
    price_range: '$35 - $55',
    thumbImagePath: 'event-images/the-circuit-thumb.webp',
};

describe('map-element-mobile.vue', () => {
    it('keeps the thumbnail a fixed 3:4 box that does not stretch with the text', () => {
        const wrapper = mount(MapElementMobile, { props: { data: event } });

        const image = wrapper.find('.aspect-\\[3\\/4\\]');
        expect(image.exists()).toBe(true);
        expect(image.classes()).toContain('self-center');
        expect(image.classes()).toContain('flex-shrink-0');
    });

    it('insets the thumbnail from the card edge and rounds its corners', () => {
        const wrapper = mount(MapElementMobile, { props: { data: event } });

        // Padding lives on the link so it wraps the image as well as the text.
        expect(wrapper.find('a').classes()).toContain('p-6');
        const image = wrapper.find('.aspect-\\[3\\/4\\]');
        expect(image.classes()).toContain('rounded-xl');
        expect(image.classes()).toContain('overflow-hidden');
    });

    it('clamps the tagline so the card cannot grow with it', () => {
        const wrapper = mount(MapElementMobile, { props: { data: event } });

        const tagline = wrapper.findAll('span').find((s) => s.text() === event.tag_line);
        expect(tagline.classes()).toContain('line-clamp-2');
    });

    it('links to the event and shows its name and price', () => {
        const wrapper = mount(MapElementMobile, { props: { data: event } });

        expect(wrapper.find('a').attributes('href')).toContain('/events/the-circuit');
        expect(wrapper.text()).toContain(event.name);
        expect(wrapper.text()).toContain('$35 - $55');
    });
});
