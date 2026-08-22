<template>
    <!-- isolate: Leaflet's own CSS gives tile images mix-blend-mode: plus-lighter
         (a documented Chromium workaround, see leaflet.css) — without an isolated
         stacking context here, that blend mode can visually bleed through and
         paint over unrelated page content (e.g. the nav dropdown) regardless of
         z-index, which is what "the map is on top of the nav" actually was. -->
    <div class="isolate w-full h-full rounded-3xl overflow-hidden bg-neutral-100">
        <template v-if="points.length">
            <l-map
                :zoom="zoom"
                :center="center"
                :options="{ scrollWheelZoom: false, zoomControl: true }"
                @ready="onMapReady"
            >
                <l-tile-layer :url="url" />
                <l-marker
                    v-for="point in points"
                    :key="point.id"
                    :icon="iconFor(point)"
                    :lat-lng="[point.lat, point.lon]"
                    @click="$emit('select', point.id)"
                >
                </l-marker>
            </l-map>
        </template>
        <div v-else class="w-full h-full flex items-center justify-center text-neutral-400 text-center px-8">
            None of these events have a set location to show on a map.
        </div>
    </div>
</template>

<script setup>
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { LMap, LTileLayer, LMarker } from '@vue-leaflet/vue-leaflet';
import { computed, ref, watch, nextTick } from 'vue';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

defineEmits(['select']);

// Same tile/marker setup as show-map.vue, adapted for multiple pins.
const zoom = 13;
const url = 'https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU';
const imageUrl = import.meta.env.VITE_IMAGE_URL;

// location_latlon comes through as JSON with lat/lon stored as strings (see
// Location::storeEventLocation) — coercing to Number here matters beyond
// correctness: summing un-coerced strings in the reduce below is JS string
// concatenation, not addition, which produced "NaN" lat/lng crashes in
// Leaflet before this was numeric. Also drop (0, 0) — an unset-coordinate
// placeholder, not a real pin (Null Island).
const points = computed(() =>
    props.events
        .filter((event) => event.hasLocation)
        .map((event) => ({
            id: event.id,
            lat: Number(event.location_latlon?.lat),
            lon: Number(event.location_latlon?.lon),
            image: event.largeImagePath ? `${imageUrl}${event.largeImagePath.slice(0, -4)}jpg` : null,
        }))
        .filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lon) && (point.lat !== 0 || point.lon !== 0))
);

// Initial view: first point, fixed zoom (matches show-map.vue's own event
// page for the single-point case). For 2+ points, onMapReady below calls
// fitBounds() imperatively on the real Leaflet instance right after — the
// declarative `bounds` prop on <l-map> turned out unreliable here (the map
// stayed blank with no console error when bounds was already set before the
// map's first render), so this uses the same @ready + fitBounds pattern
// vue-leaflet exposes its own Leaflet instance through, rather than trying
// to make the reactive prop path work.
const center = computed(() => (points.value[0] ? [points.value[0].lat, points.value[0].lon] : [0, 0]));

// The same map instance persists across an `events` prop change (e.g.
// drilling into one saved event and then going back to the full list) — only
// fitting bounds on the initial @ready left the view stuck on whichever
// point set was current when the map first mounted. Re-fitting whenever
// `points` itself changes covers both directions: zooming in to one pin and
// zooming back out to the full set.
const mapInstance = ref(null);

const fitToPoints = async () => {
    if (!mapInstance.value) return;

    if (points.value.length === 0) return;

    // The map container's actual size can change slightly across a re-render
    // (e.g. switching between the list and a single event's detail view can
    // shift the layout enough to affect a scrollbar) — Leaflet caches its
    // container size internally, so a stale cache here was the real cause of
    // fitBounds()/setView() computing the wrong view after going back to the
    // full list. Wait a tick for the DOM to settle, then force Leaflet to
    // re-measure before fitting.
    await nextTick();
    mapInstance.value.invalidateSize();

    if (points.value.length === 1) {
        mapInstance.value.setView([points.value[0].lat, points.value[0].lon], zoom);
        return;
    }

    const bounds = L.latLngBounds(points.value.map((p) => [p.lat, p.lon]));
    mapInstance.value.fitBounds(bounds, { padding: [40, 40] });
};

const onMapReady = (instance) => {
    mapInstance.value = instance;
    fitToPoints();
};

watch(points, fitToPoints);

// Escapes only the characters that could break out of the double-quoted src
// attribute below — `image` is built from our own VITE_IMAGE_URL plus a
// system-generated storage path, not free-text user input, but the marker
// HTML is injected as raw innerHTML by Leaflet, so it's escaped anyway.
const escapeAttr = (value) => value.replace(/&/g, '&amp;').replace(/"/g, '&quot;');

// Airbnb's own trip-map pins are square photo thumbnails, not plain dots —
// falls back to the old dot marker when an event has no image to show.
const iconFor = (point) => point.image
    ? L.divIcon({
        className: 'custom-div-icon',
        html: `
            <div style="width: 44px; height: 44px; border-radius: 12px; overflow: hidden; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3); background: #e5e5e5;">
                <img src="${escapeAttr(point.image)}" alt="" style="width: 100%; height: 100%; object-fit: cover; display: block;">
            </div>
        `,
        iconSize: [44, 44],
        iconAnchor: [22, 22],
    })
    : L.divIcon({
        className: 'custom-div-icon',
        html: `
            <div style="position: relative; width: 30px; height: 30px;">
                <div style="
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    width: 20px;
                    height: 20px;
                    background: #222222;
                    border: 3.5px solid white;
                    border-radius: 50%;
                    box-shadow: 0 0 0 5px #222222;
                "></div>
            </div>
        `,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
    });
</script>

<style>
/* Same custom zoom-control look as Search/Components/map.vue — rounded pill,
   soft shadow, no default Leaflet border, instead of the bare default. */
.leaflet-bar {
    border: none !important;
    margin: 0 !important;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 2px 16px rgb(0 0 0 / 12%) !important;
}
.leaflet-touch .leaflet-bar a {
    width: 40px;
    height: 40px;
    line-height: 40px;
}
</style>
