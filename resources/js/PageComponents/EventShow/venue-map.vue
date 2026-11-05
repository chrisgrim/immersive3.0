<template>
    <div class="w-full h-[30rem] rounded-2xl overflow-hidden relative z-0">
        <l-map
            :zoom="14"
            :center="center"
            :use-global-leaflet="false"
            :style="{ height: '100%', width: '100%' }"
            :options="{ scrollWheelZoom: false }"
        >
            <l-tile-layer :url="url" />
            <l-marker :lat-lng="center" />
        </l-map>
    </div>
</template>

<script setup>
import 'leaflet/dist/leaflet.css';
import { LMap, LTileLayer, LMarker } from '@vue-leaflet/vue-leaflet';
import { computed } from 'vue';

const props = defineProps({
    venue: { type: Object, required: true },
});

const center = computed(() => [parseFloat(props.venue.latitude), parseFloat(props.venue.longitude)]);

const url = 'https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU';
</script>
