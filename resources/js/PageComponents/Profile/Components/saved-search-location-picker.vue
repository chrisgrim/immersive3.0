<template>
    <div>
        <!-- Search/Components/city-search-input.vue — built to match the
             nav's own location-search.vue as closely as possible, but nav
             has its own separate inline implementation, not this component;
             the two are kept in sync by hand. -->
        <CitySearchInput v-model="query" placeholder="Search by city" @select="handleCitySelect" />

        <!-- isolate: Leaflet gives tiles mix-blend-mode: plus-lighter (see
             hub-events-map.vue's identical note) — without this the tiles can
             bleed through and paint over unrelated page content regardless of
             z-index. -->
        <div class="relative isolate mt-4 h-96 rounded-2xl overflow-hidden bg-neutral-100">
            <l-map
                ref="mapRef"
                :zoom="zoom"
                :center="center"
                style="height:100%; width:100%;"
                :options="{ scrollWheelZoom: false, zoomControl: true }"
                @moveend="onMoveEnd"
            >
                <l-tile-layer :url="tileUrl" />
            </l-map>
            <!-- A fixed pin at the map's own center, not a draggable Leaflet
                 marker — panning or zooming the MAP is what sets the location
                 (Chris: "zoom out on the map to get a location"), which is
                 simpler and more predictable than tracking a separately
                 draggable marker's own position. -->
            <div class="pointer-events-none absolute inset-0 z-[1000] flex items-center justify-center" style="margin-top: -18px;">
                <div class="w-8 h-8 rounded-full bg-default-red border-[3px] border-white shadow-custom-6"></div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { LMap, LTileLayer } from '@vue-leaflet/vue-leaflet';
import 'leaflet/dist/leaflet.css';
import CitySearchInput from '@/PageComponents/Search/Components/city-search-input.vue';

const props = defineProps({
    lat: { type: Number, default: null },
    lng: { type: Number, default: null },
    city: { type: String, default: null },
});

// `located` carries the single source of truth for this mode's fields —
// {lat, lng, city} from typing a real city, or {lat, lng, city, live,
// NElat, NElng, SWlat, SWlng} from panning/zooming to a "custom map search"
// (see buildMapBoundaryFilter() server-side, which is what actually turns
// those four into a real bounding-box filter on replay). The parent just
// stores whatever this last emitted, so the two input methods can never
// leave the draft in a mixed state.
const emit = defineEmits(['located']);

// Same Jawg tile source used everywhere else in this codebase (hub-events-map.vue,
// the creation wizard's location.vue).
const tileUrl = 'https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU';

const hasInitialLocation = props.lat != null && props.lng != null;
// No saved location yet: start zoomed out on the continental US (this is a
// US-centric app) so panning to find a city is the natural next move, rather
// than a street-level view of an arbitrary default point.
const zoom = ref(hasInitialLocation ? 13 : 4);
const center = ref(hasInitialLocation ? [props.lat, props.lng] : [39.8283, -98.5795]);

const query = ref(props.city || '');
const mapRef = ref(null);

// setView() from handleCitySelect below fires its own moveend — without this
// it would immediately re-trigger "custom map search" for the exact spot the
// typed pick just resolved via CitySearchInput's own (more precise) Places
// lookup.
let suppressNextMoveEnd = false;

// Panning/zooming the map itself sets a "custom map search" — bounds read
// straight off the Leaflet instance (no network round-trip needed, unlike
// resolving a typed city), replacing whatever city/bounds were there before.
const onMoveEnd = () => {
    if (suppressNextMoveEnd) {
        suppressNextMoveEnd = false;
        return;
    }
    if (!mapRef.value?.leafletObject) return;

    const map = mapRef.value.leafletObject;
    const c = map.getCenter();
    const bounds = map.getBounds();

    query.value = 'Custom Map Search';
    emit('located', {
        lat: c.lat,
        lng: c.lng,
        city: 'Custom Map Search',
        live: true,
        NElat: bounds.getNorthEast().lat,
        NElng: bounds.getNorthEast().lng,
        SWlat: bounds.getSouthWest().lat,
        SWlng: bounds.getSouthWest().lng,
    });
};

// CitySearchInput emits `select` twice per pick (a provisional { lat: null,
// lng: null } immediately, then the precise coordinates once they resolve —
// see its own comment) — only the precise one is actionable here. Picking a
// real city exits "custom map search" mode — a stale bounding box left over
// from an earlier drag would otherwise silently keep filtering results to
// that old area underneath the new city.
const handleCitySelect = (place) => {
    if (!Number.isFinite(place.lat) || !Number.isFinite(place.lng)) return;

    suppressNextMoveEnd = true;
    zoom.value = 13;
    center.value = [place.lat, place.lng];
    if (mapRef.value?.leafletObject) {
        mapRef.value.leafletObject.setView([place.lat, place.lng], 13);
    }
    emit('located', {
        lat: place.lat,
        lng: place.lng,
        city: place.name,
        live: false,
        NElat: null,
        NElng: null,
        SWlat: null,
        SWlng: null,
    });
};
</script>

<style scoped>
/* Zoom controls default to Leaflet's top-left corner, which sits right on
   top of the fixed center pin at this map's smaller size — same right-offset
   repositioning the creation wizard's own location.vue uses. Scoped with
   :deep() (rather than a bare global <style>, which is what location.vue and
   hub-events-map.vue both use) so this doesn't leak onto every OTHER
   Leaflet map sharing this page's bundle, like the Liked Events map. */
:deep(.leaflet-left) {
    right: 1rem !important;
    left: auto !important;
}
:deep(.leaflet-top) {
    top: 1rem !important;
}
</style>
