/**
 * Specs for PageComponents/Creation/Core/Pages/navSidebar.vue, the event
 * editor's sidebar. Covers only the Tickets card: it reads the event's own
 * tier set and must update when a save response is Object.assign-ed onto
 * the reactive event (Core/edit.vue), or the host sees stale prices.
 */
import { mount } from '@vue/test-utils';
import { h, nextTick, reactive } from 'vue';
import NavSidebar from '@/PageComponents/Creation/Core/Pages/navSidebar.vue';

// Same reason as hub-events-map.spec.js: leaflet's .png imports can't load in Node.
vi.mock('leaflet', () => ({ default: { divIcon: vi.fn(() => ({})), icon: vi.fn(() => ({})) } }));
vi.mock('@vue-leaflet/vue-leaflet', () => ({
    LMap: { name: 'LMap', render() { return h('div', this.$slots.default ? this.$slots.default() : []); } },
    LTileLayer: { name: 'LTileLayer', render() { return h('div'); } },
    LMarker: { name: 'LMarker', render() { return h('div'); } },
}));

function mountSidebar(event) {
    return mount(NavSidebar, {
        props: { event, currentStep: 'Tickets', user: {} },
        global: { stubs: { transition: false } },
    });
}

describe('navSidebar tickets card', () => {
    it('shows no tickets for a brand new event', () => {
        const wrapper = mountSidebar(reactive({ tickets: [], shows: [] }));

        expect(wrapper.text()).toContain('No tickets set');
    });

    it('uses the event set, not a stale show copy', () => {
        const wrapper = mountSidebar(reactive({
            tickets: [{ name: 'GA', ticket_price: '25.00', currency: 'USD' }],
            shows: [{ date: '2026-10-01', tickets: [{ name: 'Old', ticket_price: '99.00', currency: 'USD' }] }],
        }));

        expect(wrapper.text()).toContain('1 ticket type');
        expect(wrapper.text()).toContain('$25');
        expect(wrapper.text()).not.toContain('$99');
    });

    it('updates when a save response is assigned onto the event', async () => {
        const event = reactive({ tickets: [], shows: [] });
        const wrapper = mountSidebar(event);

        Object.assign(event, {
            tickets: [
                { name: 'General', ticket_price: '20.00', currency: 'USD' },
                { name: 'VIP', ticket_price: '50.00', currency: 'USD' },
            ],
        });
        await nextTick();

        expect(wrapper.text()).toContain('2 ticket types');
        expect(wrapper.text()).toContain('$20');
        expect(wrapper.text()).toContain('$50');
    });
});
