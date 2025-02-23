<template>
    <section
        id="location"
        class="py-16 border-b border-neutral-200">
        <template v-if="event.hasLocation">
            <h2 class="text-4xl font-medium text-black mb-8">Location</h2>
            <div v-if="event.location.hiddenLocationToggle">
                <a 
                    rel="noreferrer" 
                    target="_blank" 
                    class="text-[#222222] hover:text-neutral-700 transition-colors"
                    :href="`http://maps.google.com/maps?q=${location.city?location.city:''},+${location.region?location.region:''}`">
                    <p class="font-medium mb-2 underline" v-if="event.location.venue">{{ event.location.venue }}</p>
                    <p class="mb-2"><span v-if="event.location.city">{{ event.location.city }},</span> <span v-if="event.location.region">{{ event.location.region }}</span></p>
                    <p>{{ event.location.hiddenLocation }}</p>
                </a>
            </div>
            <div v-else>
                <a 
                    rel="noreferrer" 
                    target="_blank" 
                    class="text-[#222222] hover:text-neutral-700 transition-colors"
                    :href="`http://maps.google.com/maps?q=${location.home?location.home:''}+${location.street?location.street:''},+${location.city?location.city:''},+${location.region?location.region:''}`">
                    <p class="font-medium mb-2 underline" v-if="event.location.venue">{{ event.location.venue }}</p>
                    <p class="mb-2">{{ location.home }} {{ location.street }} {{ location.city }}</p>
                    <p>{{ location.region }} {{ location.country }} {{ location.postal_code }}</p>
                </a>
            </div>
            <div class="mt-8">
                <template v-if="center">
                    <div class="w-full h-[50rem] rounded-2xl overflow-hidden">
                        <l-map 
                            :zoom="zoom" 
                            :center="center" 
                            :options="{ scrollWheelZoom: false, zoomControl: true }">
                            <l-tile-layer :url="url" />
                            <l-marker 
                                :icon="icon"
                                :lat-lng="center">
                            </l-marker>
                        </l-map>
                    </div>  
                </template>
            </div>
        </template>
        <template v-else>
            <h2 class="text-2xl font-medium text-black mb-8">What you will need</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
                <div 
                    v-for="remote in event.remotelocations"
                    :key="remote.id"
                    class="border-2 border-[#222222] p-6 rounded-2xl hover:bg-neutral-50 transition-all duration-200">
                    <h4 class="text-lg font-medium text-black mb-4">{{ remote.name }}</h4>
                    <p class="text-[#222222]">{{ remote.description }}</p>
                </div>
            </div>
            <div 
                v-if="event.remote_description"
                class="border-2 border-[#222222] p-6 rounded-2xl hover:bg-neutral-50 transition-all duration-200 max-w-3xl">
                <h4 class="text-lg font-medium text-black mb-4">Additional Instructions</h4>
                <p class="text-[#222222] whitespace-pre-wrap">{{ event.remote_description }}</p>
            </div>
        </template>
    </section>
</template>

<script setup>
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { LMap, LTileLayer, LMarker } from "@vue-leaflet/vue-leaflet";
import { ref, onMounted } from 'vue';

const props = defineProps({
    event: {
        type: Object,
        required: true
    }
});

const zoom = ref(13);
const center = ref(props.event.location_latlon);
const location = ref(initializeLocationObject());

const icon = L.divIcon({
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
            <div style="
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 40px;
                height: 40px;
                background: rgba(34, 34, 34, 0.35);
                border-radius: 50%;
                animation: markerPulse 2s infinite;
            "></div>
        </div>
        <style>
            @keyframes markerPulse {
                0% {
                    transform: translate(-50%, -50%) scale(1);
                    opacity: 1;
                }
                100% {
                    transform: translate(-50%, -50%) scale(3);
                    opacity: 0;
                }
            }
        </style>
    `,
    iconSize: [30, 30],
    iconAnchor: [15, 15]
});

const url = "https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU";

function initializeLocationObject() {
    return {
        home: '',
        street: '',
        city: '',
        region: '',
        country: '',
        postal_code: ''
    }
}

onMounted(() => {
    Object.assign(location.value, props.event.location);
});
</script>

<style>
.leaflet-left {
    right: 3rem !important;
    left: auto !important;
}
.leaflet-top {
    top: 2rem;
}
.leaflet-bar {
    @apply !border-none !m-0 !rounded-2xl overflow-hidden !shadow-custom-6;
}
.leaflet-touch .leaflet-bar a {
    @apply !w-10 !h-10 !leading-10 !text-[#222222] hover:!bg-neutral-50 transition-colors;
}
.leaflet-control-attribution {
    @apply !hidden;
}
.leaflet-container {
    @apply !font-sans;
}
</style>