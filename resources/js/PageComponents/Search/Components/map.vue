<template>
    <section 
        :class="[ isFullMap ? 'w-full overflow-hidden mt-32' : 'w-[41%]' ]"
        class="fixed h-[calc(100vh-8rem)] right-0 top-0">
        <div 
            :class="[ isFullMap ? 'relative' : 'sticky top-32' ]"
            class="search__map overflow-hidden w-full h-full">
            <!-- Loading Spinner -->
            <div 
                v-show="isLoading"
                class="flex items-center justify-center absolute h-full w-full z-[1001]">
                <div class="bg-white shadow-custom-1 w-16 h-16 rounded-full flex items-center justify-center">
                    <div
                        class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-current border-r-transparent align-[-0.125em] motion-reduce:animate-[spin_1.5s_linear_infinite]"
                        role="status">
                        <span
                            class="!absolute !-m-px !h-px !w-px !overflow-hidden !whitespace-nowrap !border-0 !p-0 ![clip:rect(0,0,0,0)]">Loading...</span>
                    </div>
                </div>
            </div>

            <!-- Toggle Map Button -->
            <div class="absolute z-[500] left-12 top-12">
                <button @click="toggleMap" class="bg-white flex border-none h-16 w-16 rounded-2xl items-center justify-center shadow-custom-1">
                    <svg class="h-12 w-12">
                        <use :xlink:href="isFullMap ? '/storage/website-files/icons.svg#ri-arrow-right-s-line' : '/storage/website-files/icons.svg#ri-arrow-left-s-line'" />
                    </svg>
                </button>
            </div>

            <!-- Map Container -->
            <div class="w-full h-full relative">
                <l-map
                    ref="map"
                    :zoom="mapState.zoom"
                    :center="mapState.center"
                    :maxZoom="mapState.max" 
                    :minZoom="mapState.min"
                    @moveend="mapMoved"
                    :options="{ scrollWheelZoom: false, zoomControl: true }"
                    class="!w-full !h-full !absolute !inset-0">
                    <l-tile-layer :url="mapState.url" :attribution="mapState.attribution" />
                    <marker-cluster :options="{ maxClusterRadius: 40 }">
                        <l-marker v-for="event in events" :key="event.id" :lat-lng="event.location_latlon">
                            <l-icon :iconSize="[getMarkerWidth(event), 30]" :iconAnchor="[getMarkerWidth(event)/2, 4]" class-name="icons">
                                <p :class="[
                                    'font-semibold px-4 py-1 rounded-full border-2 border-black text-center inline-block min-w-[3rem] whitespace-nowrap',
                                    isSelected(event) ? 'bg-black text-white' : 'bg-white text-black'
                                ]">
                                    {{ getFixedPrice(event) }}
                                </p>
                            </l-icon>
                            <l-popup>
                                <popup-content :data="event"/>
                            </l-popup>
                        </l-marker>
                    </marker-cluster>
                </l-map>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import 'leaflet/dist/leaflet.css'
import { LMap, LTileLayer, LMarker, LPopup, LIcon } from '@vue-leaflet/vue-leaflet'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'
import MarkerCluster from './LMarkerCluster.vue'
import PopupContent from "./map-element.vue"
import MapStore from '@/Stores/MapStore.vue'

// Props & Emits
const props = defineProps({
    modelValue: { 
        type: Boolean, 
        required: true 
    },
    events: { 
        type: Array, 
        required: true 
    }
});

const emit = defineEmits(['update:modelValue', 'fullMap']);

// Refs
const map = ref(null);
const selectedMarker = ref(null);
const isLoading = ref(false);

// Map Configuration and State
const params = new URLSearchParams(window.location.search);
const lat = parseFloat(params.get('lat'));
const lng = parseFloat(params.get('lng'));

const mapState = ref({
    zoom: 13,
    center: (!isNaN(lat) && !isNaN(lng)) ? [lat, lng] : [34.0549076, -118.242643],
    max: 20,
    min: 8,
    url: "https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU",
    attribution: '<a href="http://jawg.io" title="Tiles Courtesy of Jawg Maps" target="_blank">&copy; <b>Jawg</b>Maps</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
});

// Computed
const isFullMap = computed(() => props.modelValue);

// Methods
const getFixedPrice = (event) => event.price_range.replace(/\d+(\.\d{1,2})?/g, dec => parseInt(dec));
const getMarkerWidth = (event) => getFixedPrice(event).length * 12 + 32;
const isSelected = (event) => selectedMarker.value === event.id;

const toggleMap = () => {
    console.log('toggle map fired');
    emit('update:modelValue', !props.modelValue);
    nextTick(() => {
        if (map.value?.leafletObject) {
            map.value.leafletObject.invalidateSize();
            mapMoved();
        }
    });
};

const mapMoved = () => {
    nextTick(() => {
        console.log('map moved fired');
        const currentBounds = map.value?.leafletObject?.getBounds();
        const center = map.value?.leafletObject?.getCenter();
        if (currentBounds && center) {
            MapStore.boundsUpdate(currentBounds, center);
        }
    });
};

</script>

<style>
.icons { @apply bg-transparent border-0 shadow-none !important; }
.leaflet-container { @apply !w-full !h-full !absolute !inset-0; }
.search__map { @apply relative h-full; }
.leaflet-pane .leaflet-div-icon { background: transparent; border: none; }
.leaflet-popup-content-wrapper { @apply !p-0 !rounded-2xl; }
.leaflet-popup-close-button { display: none; }
.leaflet-popup-content {
    @apply !w-[300px] !m-0;
    margin: 0;
    border-radius: 1rem;
    overflow: hidden;
}

.leaflet-left {
    right: 3rem !important;
    left: auto !important;
}
.leaflet-top {
    top: 2rem;
}
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