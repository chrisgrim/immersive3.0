/**
 * The card grid links a card to its public_url, because a run's address is
 * under its show, and shows a show on an organizer's page as a stack with a
 * count, never more than three deep.
 */
import { mount } from '@vue/test-utils';
import EventGrid from '@/GlobalComponents/Grid/event-grid.vue';

vi.mock('@/GlobalComponents/favorite-event.vue', () => ({ default: { template: '<i data-favorite />' } }));

const grid = (items) => mount(EventGrid, { props: { items, columns: 3 } });

describe('event-grid.vue', () => {
    it('links a run to its address under the show', () => {
        const wrapper = grid([{ id: 1, slug: 'x', name: 'X', public_url: '/events/show/x-2026', isShowing: true }]);

        expect(wrapper.find('a').attributes('href')).toBe('/events/show/x-2026');
    });

    it('falls back to the slug for a cached card with no public_url', () => {
        expect(grid([{ id: 1, slug: 'x', name: 'X', isShowing: true }]).find('a').attributes('href')).toBe('/events/x');
    });

    it('draws a show as a stack with its count and the Original mark, and offers no heart', () => {
        const wrapper = grid([{
            id: 'project-1', slug: 's', name: 'S', public_url: '/events/s', isShowing: true,
            show: { original: true, runs: 8, depth: 3 },
        }]);

        expect(wrapper.findAll('[data-stack-layer]')).toHaveLength(2);
        expect(wrapper.text()).toContain('Original');
        expect(wrapper.text()).toContain('8 runs');
        expect(wrapper.find('[data-favorite]').exists()).toBe(false);
    });

    it('a presented show has no Original mark, and a one-run show no stack', () => {
        const wrapper = grid([{
            id: 'project-1', slug: 's', name: 'S', public_url: '/events/s', isShowing: true,
            show: { original: false, runs: 1, depth: 1 },
        }]);

        expect(wrapper.findAll('[data-stack-layer]')).toHaveLength(0);
        expect(wrapper.text()).not.toContain('Original');
        expect(wrapper.text()).not.toContain('runs');
    });

    it('leaves a plain listing card as it was', () => {
        const wrapper = grid([{ id: 1, slug: 'x', name: 'X', public_url: '/events/x', isShowing: true }]);

        expect(wrapper.find('[data-favorite]').exists()).toBe(true);
        expect(wrapper.findAll('[data-stack-layer]')).toHaveLength(0);
    });
});
