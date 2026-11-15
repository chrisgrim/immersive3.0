<template>
    <div class="venue-map w-full overflow-hidden relative z-0" :class="boxClass">
        <l-map
            :zoom="14"
            :center="center"
            :use-global-leaflet="false"
            :style="{ height: '100%', width: '100%' }"
            :options="{ scrollWheelZoom: false }"
        >
            <l-tile-layer :url="url" />
            <l-marker :lat-lng="center" :icon="icon" />
        </l-map>
    </div>
</template>

<script setup>
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { LMap, LTileLayer, LMarker } from '@vue-leaflet/vue-leaflet';
import { computed } from 'vue';

const props = defineProps({
    venue: { type: Object, required: true },
    // Size and rounding of the box; the venue page sets its own.
    boxClass: { type: String, default: 'h-[30rem] rounded-2xl' },
});

// The event page's pin, without its pulse: Leaflet's default image is not bundled.
const icon = L.divIcon({
    className: 'custom-div-icon',
    html: '<div style="width: 20px; height: 20px; background: #222222; border: 3.5px solid white; border-radius: 50%; box-shadow: 0 0 0 5px #222222;"></div>',
    iconSize: [20, 20],
    iconAnchor: [10, 10],
});

const center = computed(() => [parseFloat(props.venue.latitude), parseFloat(props.venue.longitude)]);

const url = 'https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU';
</script>

<style>
/* Zoom buttons as on the search map: one white box top right, + over -, soft shadow. */
.venue-map .leaflet-left {
    right: 2rem !important;
    left: auto !important;
}
.venue-map .leaflet-top {
    top: 2rem;
}
.venue-map .leaflet-bar {
    border: none !important;
    margin: 0 !important;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 2px 16px rgb(0 0 0 / 12%) !important;
}
.venue-map .leaflet-bar a,
.venue-map.leaflet-touch .leaflet-bar a,
.venue-map .leaflet-touch .leaflet-bar a {
    width: 40px;
    height: 40px;
    line-height: 40px;
}
</style>
