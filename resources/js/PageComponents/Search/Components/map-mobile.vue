<template>
    <section class="mt-32 flex left-0 right-0 top-0 bottom-auto fixed h-screen">
        <div class="search__map overflow-hidden w-full h-full">
            
            <!-- Map Container -->
            <div class="w-full h-full relative">
                <l-map
                    ref="map"
                    :zoom="modelValue.location.zoom"
                    :center="modelValue.location.center"
                    :maxZoom="mapConfig.max" 
                    :minZoom="mapConfig.min"
                    :options="{ scrollWheelZoom: false, zoomControl: true }"
                    @update:center="centerUpdate"
                    @update:bounds="boundsUpdate"
                    @update:zoom="zoomUpdate"
                    class="!w-full !h-full !absolute !inset-0">
                    <l-tile-layer :url="mapConfig.url" :attribution="mapConfig.attribution" />
                    <marker-cluster :options="{ maxClusterRadius: 40 }">
                        <l-marker 
                            v-for="event in events" 
                            :key="event.id" 
                            :lat-lng="event.location_latlon"
                            @click="handleMarkerClick(event)">
                            <l-icon :iconSize="[getMarkerWidth(event), 30]" :iconAnchor="[getMarkerWidth(event)/2, 4]" class-name="icons">
                                <p :class="[
                                    'font-semibold px-4 py-1 rounded-full border-2 border-black text-center inline-block min-w-[3rem] whitespace-nowrap',
                                    isSelected(event) ? 'bg-black text-white' : 'bg-white text-black'
                                ]">
                                    {{ getFixedPrice(event) }}
                                </p>
                            </l-icon>
                        </l-marker>
                    </marker-cluster>
                </l-map>
            </div>
        </div>

        <!-- Bottom Popup -->
        <div 
            v-if="selectedEvent"
            v-click-outside="closePopup"
            class="fixed bottom-28 left-0 right-0 bg-white m-8 rounded-2xl overflow-hidden rounded-t-3xl shadow-lg transform transition-transform duration-300 z-[1003]"
            :class="selectedEvent ? 'translate-y-0' : 'translate-y-full'"
        >
            <popup-content :data="selectedEvent"/>
        </div>
    </section>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import 'leaflet/dist/leaflet.css'
import { LMap, LTileLayer, LMarker, LIcon } from '@vue-leaflet/vue-leaflet'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'
import MarkerCluster from './LMarkerCluster.vue'
import PopupContent from "./map-element-mobile.vue"
import { ClickOutsideDirective as vClickOutside } from '@/Directives/ClickOutsideDirective'

// Props & Emits
const props = defineProps({
    modelValue: { type: Object, required: true },
    events: { type: Array, required: true }
})
const emit = defineEmits(['update:modelValue', 'submit'])

// Refs & State
const map = ref(null)
const isInitialLoad = ref(true)
const selectedMarker = ref(null)
const selectedEvent = ref(null)
let timeout = null

// Map Configuration
const mapConfig = {
    max: 20,
    min: 8,
    url: "https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU",
    attribution: '<a href="http://jawg.io" title="Tiles Courtesy of Jawg Maps" target="_blank">&copy; <b>Jawg</b>Maps</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}

// Methods
const getFixedPrice = (event) => event.price_range.replace(/\d+(\.\d{1,2})?/g, dec => parseInt(dec))
const getMarkerWidth = (event) => getFixedPrice(event).length * 12 + 32
const isSelected = (event) => selectedMarker.value === event.id

const debounce = () => {
    if (timeout) clearTimeout(timeout)
    timeout = setTimeout(() => emit('submit', true), 400)
}

// Map Event Handlers
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
    if (isInitialLoad.value) {
        isInitialLoad.value = false
        return
    }

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

const handleMarkerClick = (event) => {
    selectedEvent.value = event
    selectedMarker.value = event.id
}

const closePopup = (event) => {
    const isMarkerElement = event.target.closest('.leaflet-marker-icon') || 
                           event.target.closest('.leaflet-marker-pane') ||
                           event.target.closest('.marker-cluster') ||
                           event.target.closest('.icons') ||
                           event.target.classList.contains('font-semibold');
    
    if (isMarkerElement) return
    
    selectedEvent.value = null
    selectedMarker.value = null
}

// Watchers
watch(() => props.modelValue.location.center, async (newCenter) => {
    if (newCenter) {
        await nextTick()
        if (map.value?.leafletObject) {
            map.value.leafletObject.setView(newCenter, props.modelValue.location.zoom)
        }
    }
}, { immediate: true })

watch(() => map.value?.leafletObject, (mapInstance) => {
    if (mapInstance && props.modelValue.location.center) {
        mapInstance.setView(props.modelValue.location.center, props.modelValue.location.zoom)
    }
})

// Lifecycle
onMounted(() => {
    console.log('Component mounted, directive available:', !!vClickOutside)
    isInitialLoad.value = true
})
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

/* Add styles for bottom popup animation */
.transform {
    transform-origin: bottom;
}
</style>