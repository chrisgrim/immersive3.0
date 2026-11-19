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
 *  - onMoveEnd, against a fake Leaflet map whose setView fires moveend
 *    synchronously the way Leaflet's does: a pick is a city, late moveends
 *    that end on it are ignored, a real pan is a custom map search.
 *
 * LMap/LTileLayer are stubbed (jsdom can't run vue-leaflet here), so the
 * real-library races were checked separately in review (Codex, Fable).
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

// city-search-input.vue imports /storage/website-files/icons.svg, which
// only resolves where `php artisan storage:link` has been run. The picker's
// tests only need its modelValue prop and select event, so stand it in.
vi.mock('@/PageComponents/Search/Components/city-search-input.vue', () => ({
    default: { name: 'CitySearchInput', props: ['modelValue', 'placeholder'], emits: ['select', 'update:modelValue'], render() { return h('input'); } },
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
            area: false,
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

// onMoveEnd needs mapRef.value.leafletObject. The mocked LMap above takes a
// `fakeMap` through provide/inject-free data so these tests can stand in a
// minimal Leaflet map whose view they control.
function makeMapWrapper(fakeMap) {
    const FakeLMap = {
        name: 'LMap',
        props: ['zoom', 'center'],
        emits: ['moveend'],
        data: () => ({ leafletObject: fakeMap }),
        render() { return h('div', { class: 'l-map-stub' }); },
    };
    return mount(SavedSearchLocationPicker, {
        global: { stubs: { LMap: FakeLMap, LTileLayer: true, CitySearchInput: true } },
    });
}

// Like Leaflet: setView({animate:false}) fires moveend synchronously,
// inside the call (Map._resetView -> _moveEnd). `fire` is wired to the
// LMap stub's moveend emit once the wrapper exists.
function fakeLeafletMap() {
    const view = { lat: 39.8283, lng: -98.5795, zoom: 4 };
    const map = {
        view,
        calls: [],
        fire: () => {},
        setView([lat, lng], zoom, options) {
            map.calls.push({ lat, lng, zoom, options });
            Object.assign(view, { lat, lng, zoom });
            map.fire();
        },
        getCenter: () => ({ lat: view.lat, lng: view.lng }),
        getZoom: () => view.zoom,
        // Equirectangular stand-in for Leaflet's projection: 256px world at
        // zoom 0, doubling each level. Close enough for pixel distances.
        project(latlng, zoom) {
            const [lat, lng] = Array.isArray(latlng) ? latlng : [latlng.lat, latlng.lng];
            const k = (256 * 2 ** zoom) / 360;
            return { x: lng * k, y: -lat * k };
        },
        getBounds: () => ({
            getNorthEast: () => ({ lat: view.lat + 0.02, lng: view.lng + 0.03 }),
            getSouthWest: () => ({ lat: view.lat - 0.02, lng: view.lng - 0.03 }),
        }),
    };
    return map;
}

function mountWithMap() {
    const map = fakeLeafletMap();
    const wrapper = makeMapWrapper(map);
    const lmap = wrapper.findComponent({ name: 'LMap' });
    map.fire = () => lmap.vm.$emit('moveend');
    return { map, wrapper, lmap };
}

const LA = { lat: 34.0522, lng: -118.2437, name: 'Los Angeles, CA' };

describe('saved-search-location-picker.vue map moves', () => {
    it('keeps a typed city a city: the jump to it is not a custom map search', async () => {
        const { wrapper } = mountWithMap();

        await wrapper.findComponent(CitySearchInput).vm.$emit('select', LA);

        const located = wrapper.emitted('located');
        expect(located).toHaveLength(1);
        expect(located[0][0].city).toBe('Los Angeles, CA');
        expect(wrapper.findComponent(CitySearchInput).props('modelValue')).not.toBe('Custom Map Search');
    });

    it('moves the map exactly once, without animation, and leaves the l-map props alone', async () => {
        // The bug: the pick also changed the reactive zoom/center props, so
        // vue-leaflet moved the map a second time and that later moveend
        // got through. Only one move, and no prop change, closes that.
        const { map, wrapper, lmap } = mountWithMap();

        await wrapper.findComponent(CitySearchInput).vm.$emit('select', LA);

        expect(map.calls).toEqual([{ lat: 34.0522, lng: -118.2437, zoom: 13, options: { animate: false } }]);
        expect(lmap.props('zoom')).toBe(4);
        expect(lmap.props('center')).toEqual([39.8283, -98.5795]);
    });

    it('re-picking the same city after a pan stays a city', async () => {
        const { map, wrapper, lmap } = mountWithMap();
        const input = wrapper.findComponent(CitySearchInput);

        await input.vm.$emit('select', LA);
        map.view.lng = -118.20;
        await lmap.vm.$emit('moveend');
        await input.vm.$emit('select', LA);

        const cities = wrapper.emitted('located').map((e) => e[0].city);
        expect(cities).toEqual(['Los Angeles, CA', 'Custom Map Search', 'Los Angeles, CA']);
    });

    it('ignores a half-way moveend fired inside the jump (interrupted pan or inertia)', async () => {
        const { map, wrapper } = mountWithMap();
        const realSetView = map.setView;
        // Leaflet's setView first flushes the in-flight pan's moveend
        // wherever it had got to, then lands and fires again.
        map.setView = (latlng, zoom, options) => {
            map.view.lat = 36;
            map.view.lng = -110;
            map.fire();
            realSetView(latlng, zoom, options);
        };

        await wrapper.findComponent(CitySearchInput).vm.$emit('select', LA);

        expect(wrapper.emitted('located').map((e) => e[0].city)).toEqual(['Los Angeles, CA']);
        expect(wrapper.findComponent(CitySearchInput).props('modelValue')).not.toBe('Custom Map Search');
    });

    it('ignores a late moveend that ends on the picked city (drag release, finishing zoom)', async () => {
        const { map, wrapper, lmap } = mountWithMap();

        await wrapper.findComponent(CitySearchInput).vm.$emit('select', LA);
        // Leaflet lands on whole pixels: about a pixel off the exact spot.
        map.view.lng += 0.00008;
        await lmap.vm.$emit('moveend');
        await lmap.vm.$emit('moveend');

        expect(wrapper.emitted('located').map((e) => e[0].city)).toEqual(['Los Angeles, CA']);
    });

    it('still turns a real pan away from the typed city into a custom map search', async () => {
        const { map, wrapper, lmap } = mountWithMap();

        await wrapper.findComponent(CitySearchInput).vm.$emit('select', LA);
        map.view.lat = 34.10;
        map.view.lng = -118.30;
        await lmap.vm.$emit('moveend');

        const located = wrapper.emitted('located');
        expect(located).toHaveLength(2);
        expect(located[1][0].city).toBe('Custom Map Search');
        expect(located[1][0].live).toBe(true);
    });
});
