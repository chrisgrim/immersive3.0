<template>
    <section 
        :class="[ isFullMap ? 'w-full overflow-hidden mt-64' : 'w-[41%]' ]"
        class="fixed h-[calc(100vh-8rem)] right-0 top-0">
        <div 
            :class="[ isFullMap ? 'relative' : 'sticky top-64' ]"
            class="search__map overflow-hidden w-full h-full">
            <div 
                v-show="modelValue.loading"
                class="flex items-center justify-center absolute h-full w-full z-[1001]">
                <div class="bg-white shadow-custom-1 w-16 h-16 rounded-full flex items-center justify-center">
                    <div
                        class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-current border-r-transparent"
                        role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="absolute z-[500] left-20 top-12">
                <button 
                    @click="toggleMap"
                    class="bg-white flex border-none h-16 w-16 rounded-2xl items-center justify-center shadow-custom-1">
                    <svg class="h-12 w-12">
                        <use :xlink:href="isFullMap ? '/storage/website-files/icons.svg#ri-arrow-right-s-line' : '/storage/website-files/icons.svg#ri-arrow-left-s-line'" />
                    </svg>
                </button>
            </div>
            <div class="w-full h-full relative">
                <l-map
                    :zoom="modelValue.location.zoom"
                    :center="modelValue.location.center"
                    @update:center="centerUpdate"
                    @update:bounds="boundsUpdate"
                    @update:zoom="zoomUpdate"
                    ref="map"
                    :maxZoom="mapConfig.max" 
                    :minZoom="mapConfig.min"
                    :options="{ scrollWheelZoom: false, zoomControl: true }"
                    class="!w-full !h-full !absolute !inset-0">
                    <l-tile-layer 
                        :url="mapConfig.url" 
                        :attribution="mapConfig.attribution" />
                    <marker-cluster :options="{ maxClusterRadius: 40 }">
                        <l-marker 
                            v-for="event in events"
                            :key="event.id" 
                            :lat-lng="event.location_latlon">
                            <l-icon
                                :iconSize="[getMarkerWidth(event), 30]"
                                :iconAnchor="[getMarkerWidth(event)/2, 4]"
                                class-name="icons">
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

<style>
.icons {
    @apply bg-transparent border-0 shadow-none !important;
}

.leaflet-container {
    @apply !w-full !h-full !absolute !inset-0;
}

.search__map {
    @apply relative h-full;
}
.leaflet-pane .leaflet-div-icon {
    background: transparent;
    border: none;
}

.leaflet-popup-content-wrapper {
    @apply !p-0 !rounded-2xl;
}
.leaflet-popup-close-button {
    display: none;
}
.leaflet-popup-content {
    @apply !w-[300px] !m-0;
    margin: 0;
    border-radius: 1rem;
    overflow: hidden;
}
</style>

<script setup>
import { ref, computed } from 'vue'
import 'leaflet/dist/leaflet.css'
import L from 'leaflet'
import { LMap, LTileLayer, LMarker, LPopup, LIcon } from '@vue-leaflet/vue-leaflet'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'
import MarkerCluster from './LMarkerCluster.vue'
import PopupContent from "./map-element.vue"

const props = defineProps({
    modelValue: {
        type: Object,
        required: true
    },
    events: {
        type: Array,
        required: true
    }
})

const emit = defineEmits(['update:modelValue', 'submit', 'fullMap'])

const map = ref(null)
let timeout = null

const mapConfig = {
    max: 20,
    min: 8,
    url: "https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU",
    attribution: '<a href="http://jawg.io" title="Tiles Courtesy of Jawg Maps" target="_blank">&copy; <b>Jawg</b>Maps</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}

const markerIcon = L.icon({
    iconUrl: '/storage/images/vendor/leaflet/dist/marker-icon.png',
    iconSize: [32, 37],
    iconAnchor: [16, 37]
})

const isFullMap = computed(() => props.modelValue.location.fullMap)

const getFixedPrice = (event) => {
    return event.price_range.replace(/\d+(\.\d{1,2})?/g, dec => parseInt(dec))
}

const update = () => {
    emit('submit', true)
}

const debounce = () => {
    if (timeout) clearTimeout(timeout)
    timeout = setTimeout(() => { update() }, 400)
}

const toggleMap = () => {
    emit('fullMap')
    emit('update:modelValue', {
        ...props.modelValue,
        location: {
            ...props.modelValue.location,
            fullMap: !props.modelValue.location.fullMap
        }
    })
}

const zoomUpdate = (newZoom) => {
    emit('update:modelValue', {
        ...props.modelValue,
        location: {
            ...props.modelValue.location,
            zoom: newZoom
        }
    })
}

const centerUpdate = (center) => {
    emit('update:modelValue', {
        ...props.modelValue,
        location: {
            ...props.modelValue.location,
            center: center
        }
    })
}

const boundsUpdate = (bounds) => {
    emit('update:modelValue', {
        ...props.modelValue,
        location: {
            ...props.modelValue.location,
            mapboundary: bounds,
            live: true
        }
    })
    debounce()
}

const getMarkerWidth = (event) => {
    // Calculate approximate width based on price text
    const price = getFixedPrice(event)
    // Approximate width calculation (adjust multiplier as needed)
    return price.length * 12 + 32 // 12px per character + padding
}

// Add ref for tracking selected marker
const selectedMarker = ref(null)

// Add method to check if marker is selected
const isSelected = (event) => {
    return selectedMarker.value === event.id
}

// Add method to handle marker selection
const handleMarkerClick = (event) => {
    selectedMarker.value = event.id
}
</script>