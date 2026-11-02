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

// Moving the map to a typed city must not count as a "custom map search".
// The old one-shot "skip the next moveend" flag lost to a second move: the
// pick ALSO changed the reactive zoom/center props, so vue-leaflet's own
// watchers moved the map again, and that later moveend turned a typed city
// into a zoom-13 box (Chris, 2026-11-02: "I typed in LA but it kept going
// to custom search"). Reviews with the real libraries (Codex, Fable) found
// the other ways a move can land on the pick without being the user's:
//  - moves fired INSIDE setView (an interrupted pan or inertia flushes its
//    moveend at a half-way spot) -> ignored by movingProgrammatically;
//  - moves fired AFTER it that end on the picked view (a drag released
//    without moving, a window resize's debounced moveend) -> ignored by
//    endsOnPickedView, judged in screen pixels rather than degrees.
// The first moveend that ends anywhere else is the user's own move.
// Don't call map.stop() first: it fires its own moveend at the old view.
let movingProgrammatically = false;
let pickedView = null;
const PICK_TOLERANCE_PX = 2;

const endsOnPickedView = (map) => {
    if (!pickedView || map.getZoom() !== pickedView.zoom) return false;
    const here = map.project(map.getCenter(), pickedView.zoom);
    const picked = map.project(pickedView.latlng, pickedView.zoom);
    return Math.hypot(here.x - picked.x, here.y - picked.y) <= PICK_TOLERANCE_PX;
};

// Panning/zooming the map itself sets a "custom map search" — bounds read
// straight off the Leaflet instance (no network round-trip needed, unlike
// resolving a typed city), replacing whatever city/bounds were there before.
const onMoveEnd = () => {
    if (!mapRef.value?.leafletObject) return;

    if (movingProgrammatically) return;

    const map = mapRef.value.leafletObject;
    if (endsOnPickedView(map)) return;
    pickedView = null;
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

    pickedView = { latlng: [place.lat, place.lng], zoom: 13 };
    const map = mapRef.value?.leafletObject;
    if (map) {
        movingProgrammatically = true;
        try {
            // A zoom animation still running (zoom button, double-click,
            // pinch) makes setView return without moving, then lands on its
            // old target 250ms later. Leaflet 1.9 has no public way to end
            // it, so finish it now (private, guarded) and move from there.
            if (map._animatingZoom && typeof map._onZoomTransitionEnd === 'function') {
                map._onZoomTransitionEnd();
            }
            map.setView([place.lat, place.lng], 13, { animate: false });
        } finally {
            movingProgrammatically = false;
        }
    } else {
        // Map not created yet. vue-leaflet snapshots these props at setup,
        // so this only lands if the pick beats that (practically never);
        // left as the best effort it has always been.
        zoom.value = 13;
        center.value = [place.lat, place.lng];
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
