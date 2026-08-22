/**
 * Specs for Profile/Components/saved-search-location-picker.vue
 *
 * Covers:
 *  - Initial query/zoom/center derived from the lat/lng/city props.
 *  - handleCitySelect (CitySearchInput's @select): a precise pick emits
 *    'located' with the correct shape (live:false, bounds nulled out).
 *  - handleCitySelect ignores CitySearchInput's provisional {lat:null,
 *    lng:null} emit (fired before the real coordinates resolve).
 *
 * NOT covered: onMoveEnd (the map-pan "custom map search" path) — it reads
 * mapRef.value.leafletObject, a real Leaflet map instance that only exists
 * once <l-map> actually mounts and initializes, which jsdom/vue-leaflet
 * cannot do here even when stubbed. LMap/LTileLayer are stubbed out entirely
 * so the component can mount at all.
 */
import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import { h } from 'vue';
import { LMap } from '@vue-leaflet/vue-leaflet';
import SavedSearchLocationPicker from '@/PageComponents/Profile/Components/saved-search-location-picker.vue';
import CitySearchInput from '@/PageComponents/Search/Components/city-search-input.vue';

// The component statically imports 'leaflet/dist/leaflet.css', which
// references marker icon .png assets Vitest's default transform can't
// resolve as an ES module (unrelated to LMap/LTileLayer being stubbed below
// — this import runs at module-eval time regardless). Stub it out here.
vi.mock('leaflet/dist/leaflet.css', () => ({}));

// @vue-leaflet/vue-leaflet's own module (NOT this component) dynamically
// imports leaflet's marker-icon-2x.png/marker-icon.png/marker-shadow.png as
// an unconditional top-level side effect the moment it's imported — the same
// Node ESM ERR_UNKNOWN_FILE_EXTENSION as the CSS import above, just one level
// removed. The global stubs below (mount(..., {global: {stubs}})) don't
// prevent that side effect because the real package module still gets
// imported and executed before VTU ever gets a chance to replace the
// components with stubs — mocking the whole package here is what actually
// avoids loading it at all. Same fix as hub-events-map.spec.js.
vi.mock('@vue-leaflet/vue-leaflet', () => ({
    LMap: { name: 'LMap', props: ['zoom', 'center'], render() { return h('div', { class: 'l-map-stub' }, this.$slots.default ? this.$slots.default() : []); } },
    LTileLayer: { name: 'LTileLayer', render() { return h('div', { class: 'l-tile-layer-stub' }); } },
}));

function makeWrapper(props = {}) {
    return mount(SavedSearchLocationPicker, {
        props,
        global: {
            stubs: {
                LMap: true,
                LTileLayer: true,
                CitySearchInput: true,
            },
        },
    });
}

describe('saved-search-location-picker.vue', () => {
    it('starts query empty and zoomed out on the continental US when no lat/lng is given', () => {
        const wrapper = makeWrapper();

        const map = wrapper.findComponent(LMap);
        expect(map.props('zoom')).toBe(4);
        expect(map.props('center')).toEqual([39.8283, -98.5795]);
    });

    it('starts query from the city prop and zoomed to the given lat/lng', () => {
        const wrapper = makeWrapper({ lat: 40.7128, lng: -74.006, city: 'New York, NY' });

        expect(wrapper.findComponent(CitySearchInput).props('modelValue')).toBe('New York, NY');
        const map = wrapper.findComponent(LMap);
        expect(map.props('zoom')).toBe(13);
        expect(map.props('center')).toEqual([40.7128, -74.006]);
    });

    it('emits located with live:false and nulled bounds when a real city is picked', async () => {
        const wrapper = makeWrapper();

        await wrapper.findComponent(CitySearchInput).vm.$emit('select', {
            lat: 34.0522,
            lng: -118.2437,
            name: 'Los Angeles, CA',
        });

        expect(wrapper.emitted('located')).toBeTruthy();
        expect(wrapper.emitted('located')[0][0]).toEqual({
            lat: 34.0522,
            lng: -118.2437,
            city: 'Los Angeles, CA',
            live: false,
            NElat: null,
            NElng: null,
            SWlat: null,
            SWlng: null,
        });
    });

    it('ignores the provisional {lat:null,lng:null} select emit before coordinates resolve', async () => {
        const wrapper = makeWrapper();

        await wrapper.findComponent(CitySearchInput).vm.$emit('select', {
            lat: null,
            lng: null,
            name: 'Los Angeles, CA',
        });

        expect(wrapper.emitted('located')).toBeFalsy();
    });
});
