/**
 * Specs for PageComponents/Profile/Components/hub-events-map.vue
 *
 * Leaflet needs real canvas/layout support jsdom doesn't provide, so LMap/
 * LTileLayer/LMarker are stubbed out here — these tests cover the `points`
 * computed (the actual logic in this file: filtering by hasLocation,
 * coercing string lat/lon to numbers, dropping (0,0) placeholder
 * coordinates) via the resulting marker count and the empty-state message,
 * not real Leaflet rendering/interaction. select-on-marker-click and the
 * imperative fitBounds/setView calls (onMapReady/fitToPoints) are NOT
 * covered — they only run through the real Leaflet instance vue-leaflet
 * exposes via @ready, which the stub never fires.
 */
import { mount } from '@vue/test-utils';
import { h } from 'vue';
import HubEventsMap from '@/PageComponents/Profile/Components/hub-events-map.vue';

// The real `leaflet` package imports its default marker icon as a .png at
// module scope, which Vitest's Node ESM loader can't resolve (ERR_UNKNOWN_
// FILE_EXTENSION) — unrelated to anything this component does. Mocked here
// with just what iconFor()/fitToPoints actually call (L.divIcon,
// L.latLngBounds).
vi.mock('leaflet', () => ({
    default: {
        divIcon: vi.fn(() => ({})),
        latLngBounds: vi.fn(() => ({})),
    },
}));

// @vue-leaflet/vue-leaflet's own module (NOT this component) dynamically
// imports leaflet's marker-icon-2x.png/marker-icon.png/marker-shadow.png as
// an unconditional top-level side effect the moment it's imported — the
// same Node ESM .png error as above, just one level removed. Global stubs
// (mount(..., {global: {stubs}})) don't prevent that side effect because
// the real package module still gets imported and executed before VTU ever
// gets a chance to replace the components with stubs. Mocking the whole
// package here is what actually avoids loading it at all.
vi.mock('@vue-leaflet/vue-leaflet', () => ({
    LMap: { name: 'LMap', emits: ['ready'], render() { return h('div', { class: 'l-map-stub' }, this.$slots.default ? this.$slots.default() : []); } },
    LTileLayer: { name: 'LTileLayer', render() { return h('div', { class: 'l-tile-layer-stub' }); } },
    LMarker: { name: 'LMarker', props: ['icon', 'latLng'], emits: ['click'], render() { return h('div', { class: 'l-marker-stub', onClick: () => this.$emit('click') }); } },
}));

const stubs = {};

function makeEvent(overrides = {}) {
    return {
        id: 1,
        hasLocation: true,
        location_latlon: { lat: '34.05', lon: '-118.24' },
        largeImagePath: null,
        ...overrides,
    };
}

function mountMap(props = {}) {
    return mount(HubEventsMap, {
        props: { events: [], ...props },
        global: { stubs },
    });
}

describe('hub-events-map.vue', () => {
    it('shows the empty-state message when there are no events', () => {
        const wrapper = mountMap({ events: [] });
        expect(wrapper.text()).toContain('None of these events have a set location to show on a map.');
        expect(wrapper.findComponent({ name: 'LMap' }).exists()).toBe(false);
    });

    it('renders one marker per event with a valid, non-zero location', () => {
        const wrapper = mountMap({
            events: [
                makeEvent({ id: 1 }),
                makeEvent({ id: 2, location_latlon: { lat: '40.71', lon: '-74.00' } }),
            ],
        });

        expect(wrapper.findAllComponents({ name: 'LMarker' })).toHaveLength(2);
    });

    it('excludes events with hasLocation: false', () => {
        const wrapper = mountMap({
            events: [makeEvent({ id: 1, hasLocation: false })],
        });

        expect(wrapper.findAllComponents({ name: 'LMarker' })).toHaveLength(0);
        expect(wrapper.text()).toContain('None of these events have a set location');
    });

    it('excludes events whose lat/lon are non-numeric strings', () => {
        const wrapper = mountMap({
            events: [makeEvent({ id: 1, location_latlon: { lat: 'not-a-number', lon: '-118.24' } })],
        });

        expect(wrapper.findAllComponents({ name: 'LMarker' })).toHaveLength(0);
    });

    it('excludes the (0, 0) "Null Island" placeholder coordinate', () => {
        const wrapper = mountMap({
            events: [makeEvent({ id: 1, location_latlon: { lat: '0', lon: '0' } })],
        });

        expect(wrapper.findAllComponents({ name: 'LMarker' })).toHaveLength(0);
    });

    it('includes a point when only one of lat/lon is exactly 0 (a real coordinate on the equator/meridian)', () => {
        const wrapper = mountMap({
            events: [makeEvent({ id: 1, location_latlon: { lat: '0', lon: '-118.24' } })],
        });

        expect(wrapper.findAllComponents({ name: 'LMarker' })).toHaveLength(1);
    });

    it('renders the map (not the empty state) once at least one valid point exists', () => {
        const wrapper = mountMap({ events: [makeEvent()] });
        expect(wrapper.findComponent({ name: 'LMap' }).exists()).toBe(true);
        expect(wrapper.text()).not.toContain('None of these events have a set location');
    });

    it('emits select with the point id when a marker is clicked', async () => {
        const wrapper = mountMap({ events: [makeEvent({ id: 42 })] });
        await wrapper.findComponent({ name: 'LMarker' }).trigger('click');
        expect(wrapper.emitted('select')).toEqual([[42]]);
    });
});
