/**
 * Specs for GlobalComponents/Grid/event-grid.vue's search-click analytics:
 * on search results (searchId set) a card click sends one beacon saying
 * which search, which event and its 1-based position; anywhere else the
 * grid sends nothing.
 */
import { mount } from '@vue/test-utils';
import EventGrid from '@/GlobalComponents/Grid/event-grid.vue';

vi.mock('@/GlobalComponents/favorite-event.vue', () => ({ default: { template: '<span />' } }));

const cards = [
    { id: 11, slug: 'first', name: 'First', isShowing: true },
    { id: 22, slug: 'second', name: 'Second', isShowing: true },
];

function grid(props = {}) {
    return mount(EventGrid, {
        props: { items: cards, ...props },
        global: { stubs: { FavoriteEvent: true, 'favorite-event': true } },
    });
}

beforeEach(() => {
    navigator.sendBeacon = vi.fn(() => true);
});

test('a click on a search result beacons the search, the event and its position', async () => {
    const wrapper = grid({ searchId: 'abcDEF123456' });

    await wrapper.findAll('a')[1].trigger('click');

    expect(navigator.sendBeacon).toHaveBeenCalledTimes(1);
    const [url, body] = navigator.sendBeacon.mock.calls[0];
    expect(url).toBe('/api/analytics/search-click');
    expect(Object.fromEntries(body)).toEqual({ search_id: 'abcDEF123456', event_id: '22', position: '2' });
});

test('a grid that is not search results sends nothing', async () => {
    const wrapper = grid();

    await wrapper.findAll('a')[0].trigger('click');

    expect(navigator.sendBeacon).not.toHaveBeenCalled();
});
