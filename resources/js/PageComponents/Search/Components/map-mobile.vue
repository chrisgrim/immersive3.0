<template>
    <section :class="[
        'flex left-0 right-0 top-0 bottom-auto fixed',
        fullMap ? 'h-screen' : 'h-[54vh]'
    ]">
        <div class="search__map overflow-hidden w-full h-full">
            <!-- Loading Spinner -->
            <div 
                v-show="showLoading"
                class="flex items-center justify-center absolute h-full w-full z-[1001] pointer-events-none">
                <div class="bg-white shadow-custom-1 w-16 h-16 rounded-full flex items-center justify-center pointer-events-auto">
                    <div
                        class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-current border-r-transparent align-[-0.125em] motion-reduce:animate-[spin_1.5s_linear_infinite]"
                        role="status">
                        <span
                            class="!absolute !-m-px !h-px !w-px !overflow-hidden !whitespace-nowrap !border-0 !p-0 ![clip:rect(0,0,0,0)]">Loading...</span>
                    </div>
                </div>
            </div>

            <!-- Map Container -->
            <div class="w-full h-full relative">
                <l-map
                    ref="map"
                    :zoom="modelValue.location.zoom"
                    :center="modelValue.location.center"
                    :maxZoom="mapConfig.max" 
                    :minZoom="mapConfig.min"
                    :options="{
                        scrollWheelZoom: false,
                        zoomControl: true,
                        dragging: true,
                        tap: true,
                        touchZoom: true,
                        bounceAtZoomLimits: false,
                        touchStart: true,
                        touchMove: true,
                        touchEnd: true
                    }"
                    @update:bounds="boundsUpdate"
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
import { ref, onMounted, watch, nextTick, computed, onUnmounted } from 'vue'
import 'leaflet/dist/leaflet.css'
import { LMap, LTileLayer, LMarker, LIcon } from '@vue-leaflet/vue-leaflet'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'
import MarkerCluster from './LMarkerCluster.vue'
import PopupContent from "./map-element-mobile.vue"
import { ClickOutsideDirective as vClickOutside } from '@/Directives/ClickOutsideDirective'
import eventStore from '@/Stores/EventStore.vue'

// Props & Emits
const props = defineProps({
    modelValue: { type: Object, required: true },
    events: { type: Array, required: true },
    fullMap: { type: Boolean, default: false },
    source: { type: String, default: 'initialSearch' }
})
const emit = defineEmits(['update:modelValue', 'boundsChanged'])

// Refs & State
const map = ref(null)
const isInitialLoad = ref(true)
const selectedMarker = ref(null)
const selectedEvent = ref(null)
let timeout = null
const isProcessingBounds = ref(false)
const isUserInteraction = ref(false)
const initialLocation = ref(true)
const boundsUpdateTimeout = ref(null)
const lastBoundaryData = ref(null)
const isLoading = ref(false);

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

const boundsUpdate = (bounds) => {
    if (isProcessingBounds.value) {
        return;
    }

    // Only check if we're in initial search, don't block permanently
    if (props.source === 'initialSearch' && !isUserInteraction.value) {
        console.log('Skipping initial bounds update');
        isUserInteraction.value = true;
        return;
    }
    
    // Clear existing timeout
    if (boundsUpdateTimeout.value) {
        clearTimeout(boundsUpdateTimeout.value);
    }
    
    boundsUpdateTimeout.value = setTimeout(() => {
        isProcessingBounds.value = true;
        isLoading.value = true;
        
        const boundaryData = {
            northEast: {
                lat: bounds._northEast.lat,
                lng: bounds._northEast.lng
            },
            southWest: {
                lat: bounds._southWest.lat,
                lng: bounds._southWest.lng
            }
        };

        emit('boundsChanged', boundaryData);
        emit('update:modelValue', {
            ...props.modelValue,
            location: {
                ...props.modelValue.location,
                mapboundary: boundaryData,
                live: true
            }
        });

        setTimeout(() => {
            isProcessingBounds.value = false;
            setTimeout(() => {
                isLoading.value = false;
            }, 250);
        }, 500);
    }, 500);
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
watch(() => props.source, (newSource) => {
    if (newSource === 'initialSearch') {
        isUserInteraction.value = false;
    }
}, { immediate: true });

watch(() => map.value?.leafletObject, (mapInstance) => {
    if (initialLocation.value) {
        initialLocation.value = false;
        if (props.modelValue.location.center) {
            mapInstance.setView(props.modelValue.location.center, props.modelValue.location.zoom);
            // Set isInitialLoad to false after the view is set
            setTimeout(() => {
                isInitialLoad.value = false;
            }, 100);
        }
    }
});

watch(() => props.events, (newEvents) => {
    if (isLoading.value) {
        // Small delay to ensure smooth transition
        setTimeout(() => {
            isLoading.value = false;
        }, 250);
    }
}, { deep: true });

// Lifecycle
onMounted(() => {
    console.log('Component mounted, directive available:', !!vClickOutside)
    isInitialLoad.value = true
})

onUnmounted(() => {
    if (boundsUpdateTimeout.value) {
        clearTimeout(boundsUpdateTimeout.value);
    }
    lastBoundaryData.value = null;
})

// Computed
const showLoading = computed(() => {
    return props.modelValue.loading || isLoading.value;
});
</script>

<style>
.icons { @apply bg-transparent border-0 shadow-none !important; }
.leaflet-container { @apply !w-full !h-full !absolute !inset-0; }
.search__map { 
    @apply relative h-full;
    touch-action: pan-x pan-y pinch-zoom;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}
.leaflet-pane .leaflet-div-icon { background: transparent; border: none; }
.leaflet-popup-content-wrapper { @apply !p-0 !rounded-2xl; }
.leaflet-popup-close-button { display: none; }
.leaflet-popup-content {
    @apply !w-[300px] !m-0;
    margin: 0;
    border-radius: 1rem;
    overflow: hidden;
}



/* Add styles for bottom popup animation */
.transform {
    transform-origin: bottom;
}

/* Prevent text selection during map interaction */
.leaflet-container {
    -webkit-tap-highlight-color: transparent;
}

.leaflet-marker-icon {
    touch-action: none !important;
}

/* Improve marker cluster touch areas */
.marker-cluster {
    background-clip: padding-box;
    touch-action: none !important;
}

/* Optional: Add smooth transitions for better mobile UX */
.leaflet-fade-anim .leaflet-tile,
.leaflet-fade-anim .leaflet-popup {
    transition: opacity 0.2s linear;
}
</style>