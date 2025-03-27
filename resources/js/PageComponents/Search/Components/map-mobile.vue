<template>
    <section :class="[
        'flex left-0 right-0 top-0 bottom-auto fixed',
        isFullMap ? 'h-screen' : 'h-[54vh]'
    ]">
        <div class="search__map overflow-hidden w-full h-full">
            <!-- Loading Spinner -->
            <div 
                v-show="isLoading"
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
                <div id="leaflet-map-mobile" class="w-full h-full absolute inset-0"></div>
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
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue'
import 'leaflet/dist/leaflet.css'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'
import PopupContent from "./map-element-mobile.vue"
import { ClickOutsideDirective as vClickOutside } from '@/Directives/ClickOutsideDirective'
import MapStore from '@/Stores/MapStore.vue'
import L from 'leaflet'
import 'leaflet.markercluster/dist/leaflet.markercluster.js'

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

const emit = defineEmits(['update:modelValue']);

// Refs & State
const selectedMarker = ref(null);
const selectedEvent = ref(null);
const isLoading = ref(false);
const isFullMap = computed(() => props.modelValue);

// Leaflet objects
let map = null;
let markerClusterGroup = null;
let markers = [];

// Map Configuration
const params = new URLSearchParams(window.location.search);
const lat = parseFloat(params.get('lat'));
const lng = parseFloat(params.get('lng'));

const mapConfig = {
    zoom: 13,
    center: (!isNaN(lat) && !isNaN(lng)) ? [lat, lng] : [34.0549076, -118.242643],
    maxZoom: 20,
    minZoom: 8,
    tileUrl: "https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU",
    attribution: '<a href="http://jawg.io" title="Tiles Courtesy of Jawg Maps" target="_blank">&copy; <b>Jawg</b>Maps</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
};

// Helper Methods
const getFixedPrice = (event) => {
    if (!event || !event.price_range) return '0';
    return event.price_range.replace(/\d+(\.\d{1,2})?/g, dec => parseInt(dec));
};

// Get marker width based on price
const getMarkerWidth = (event) => {
    const price = getFixedPrice(event);
    return price.toString().length * 12 + 32; // Using your mobile sizing
};

// Check if an event is selected
const isSelected = (event) => {
    return selectedMarker.value === event.id;
};

// Get proper LatLng for an event
const getLatLng = (event) => {
    if (!event.location_latlon) return null;
    
    let lat, lng;
    if (typeof event.location_latlon === 'object') {
        lat = event.location_latlon.lat || event.location_latlon.latitude;
        lng = event.location_latlon.lon || event.location_latlon.lng || event.location_latlon.longitude;
    }
    
    if (!lat || !lng) return null;
    return L.latLng(parseFloat(lat), parseFloat(lng));
};

// Loading state management
const startLoading = () => {
    isLoading.value = true;
};

const stopLoading = () => {
    const minimumLoadingTime = 333; // 1/3 second in milliseconds
    setTimeout(() => {
        isLoading.value = false;
    }, minimumLoadingTime);
};

// Create a price marker icon
const createMarkerIcon = (event) => {
    const price = getFixedPrice(event);
    const isEventSelected = isSelected(event);
    
    // Create icon HTML with your styling
    const html = `
        <p class="font-semibold px-4 py-1 rounded-full border-2 border-black text-center inline-block min-w-[3rem] whitespace-nowrap ${isEventSelected ? 'bg-black text-white' : 'bg-white text-black'}">
            ${price}
        </p>
    `;
    
    // Determine width based on price
    const width = getMarkerWidth(event);
    
    return L.divIcon({
        html: html,
        className: 'icons custom-price-marker',
        iconSize: [width, 30],
        iconAnchor: [width / 2, 4]
    });
};

// Custom cluster icon
const createClusterIcon = (cluster) => {
    const count = cluster.getChildCount();
    
    // Make width dynamic based on count digits
    const width = count.toString().length * 12 + 32;
    
    return L.divIcon({
        html: `
            <p class="font-semibold px-4 py-1 rounded-full border-2 border-black text-center inline-block min-w-[3rem] whitespace-nowrap bg-black text-white">
                ${count}
            </p>
        `,
        className: 'icons',
        iconSize: [width, 30],
        iconAnchor: [width / 2, 15]
    });
};

// Create the map
const initMap = () => {
    startLoading();
    
    // Create the map instance with mobile-specific options
    map = L.map('leaflet-map-mobile', {
        center: mapConfig.center,
        zoom: mapConfig.zoom,
        maxZoom: mapConfig.maxZoom,
        minZoom: mapConfig.minZoom,
        zoomControl: true,
        scrollWheelZoom: false,
        zoomAnimation: true,
        fadeAnimation: true,
        tap: true,
        touchZoom: true,
        dragging: true,
        bounceAtZoomLimits: false,
        touchStart: true,
        touchMove: true,
        touchEnd: true
    });
    
    // Add the tile layer
    L.tileLayer(mapConfig.tileUrl, {
        attribution: mapConfig.attribution
    }).addTo(map);
    
    // Create marker cluster group
    markerClusterGroup = L.markerClusterGroup({
        maxClusterRadius: 40, // Match your original value
        iconCreateFunction: createClusterIcon,
        animate: true,
        animateAddingMarkers: false,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true,
        spiderfyDistanceMultiplier: 2,
        disableClusteringAtZoom: 18,
        spiderfyOnEveryZoom: false,
        removeOutsideVisibleBounds: true,
        zoomToBoundsOnClick: true
    });
    
    // Add cluster group to map
    map.addLayer(markerClusterGroup);
    
    // Set up map event listeners
    map.on('moveend', mapMoved);
    map.on('movestart', () => {
        // Prevent marker transitions during movement
        document.querySelectorAll('.leaflet-marker-icon').forEach(el => {
            el.style.transition = 'none';
        });
    });
    map.on('zoomstart', () => {
        // Prevent the brief flickering of markers during zoom
        document.querySelectorAll('.leaflet-marker-icon').forEach(el => {
            if (el.classList.contains('custom-price-marker')) {
                el.style.opacity = '0';
            }
        });
    });
    map.on('zoomend', () => {
        // Restore marker visibility after zoom completes
        setTimeout(() => {
            document.querySelectorAll('.leaflet-marker-icon').forEach(el => {
                if (el.classList.contains('custom-price-marker')) {
                    el.style.opacity = '1';
                    el.style.transition = 'opacity 0.15s ease-in-out';
                }
            });
        }, 50);
    });
    
    // Create markers
    createMarkers(props.events);
    
    // Notify that map is ready
    console.log('Mobile map initialized');
};

// Create markers for events
const createMarkers = (events) => {
    startLoading();
    
    // Clear existing markers
    markerClusterGroup.clearLayers();
    markers = [];
    
    // Filter to valid events with coordinates
    const validEvents = events.filter(event => {
        if (!event.location_latlon) return false;
        
        let lat, lng;
        if (typeof event.location_latlon === 'object') {
            lat = event.location_latlon.lat || event.location_latlon.latitude;
            lng = event.location_latlon.lon || event.location_latlon.lng || event.location_latlon.longitude;
        }
        
        return lat && lng;
    });
    
    validEvents.forEach(event => {
        const latLng = getLatLng(event);
        if (!latLng) return;
        
        const marker = L.marker(latLng, {
            icon: createMarkerIcon(event)
        });
        
        // Store event data with marker
        marker.eventData = event;
        
        // Add click handler for mobile experience - show bottom popup
        marker.on('click', () => {
            selectedMarker.value = event.id;
            selectedEvent.value = event;
            
            // Update marker styles
            updateMarkerStyles();
        });
        
        // Add to cluster group
        markerClusterGroup.addLayer(marker);
        markers.push(marker);
    });
    
    console.log(`Created ${markers.length} markers for mobile`);
    
    // Stop loading with the minimum display time
    stopLoading();
};

// Update marker styles
const updateMarkerStyles = () => {
    markers.forEach(marker => {
        marker.setIcon(createMarkerIcon(marker.eventData));
    });
};

// Map moved event handler
const mapMoved = () => {
    if (!map) return;
    
    const bounds = map.getBounds();
    const center = map.getCenter();
    
    if (bounds && center) {
        MapStore.boundsUpdate(bounds, center);
    }
};

// Close popup handler
const closePopup = (event) => {
    const isMarkerElement = event.target.closest('.leaflet-marker-icon') || 
                           event.target.closest('.leaflet-marker-pane') ||
                           event.target.closest('.marker-cluster') ||
                           event.target.closest('.icons') ||
                           event.target.classList.contains('font-semibold');
    
    if (isMarkerElement) return;
    
    selectedEvent.value = null;
    selectedMarker.value = null;
};

// Initialize the map on component mount
onMounted(() => {
    // Use a short delay to ensure the DOM is ready
    setTimeout(() => {
        initMap();
    }, 0);
});

// Watch for changes to events
watch(() => props.events, (newEvents, oldEvents) => {
    if (map && markerClusterGroup) {
        // Only recreate if events actually changed
        if (newEvents !== oldEvents) {
            startLoading();
            createMarkers(newEvents);
        }
    }
}, { deep: true });

// Watch for selection changes
watch(() => selectedMarker.value, () => {
    updateMarkerStyles();
});

// Clean up on component unmount
onBeforeUnmount(() => {
    if (map) {
        map.off('moveend', mapMoved);
        map.remove();
        map = null;
    }
    
    markerClusterGroup = null;
    markers = [];
});

// Expose loading state control to parent component
defineExpose({
    startLoading,
    stopLoading
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

/* Custom popup styles */
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

/* Shadow utility */
.shadow-custom-1 {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
</style>