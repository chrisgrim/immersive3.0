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
                <div id="leaflet-map" class="w-full h-full absolute inset-0"></div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue'
import 'leaflet/dist/leaflet.css'
import MapElement from './map-element.vue'
import MapStore from '@/Stores/MapStore.vue'
import L from 'leaflet'
import 'leaflet.markercluster/dist/leaflet.markercluster.js'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'
import { createApp } from 'vue'

// Props & Emits
const props = defineProps({
    modelValue: { 
        type: Boolean, 
        required: true 
    },
    events: { 
        type: Array, 
        required: true 
    },
    isDebug: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'fullMap', 'markerClick']);

// Refs
const selectedMarker = ref(null);
const isLoading = ref(false);
const markerCount = ref(0);
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
    return price.toString().length * 10 + 30;
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
    const width = count.toString().length * 10 + 30;
    
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

// Create a separate function to handle data fetching loading states
const startLoading = () => {
    isLoading.value = true;
};

const stopLoading = () => {
    // Get the current time
    const currentTime = new Date().getTime();
    
    // Calculate time since loading started (if we have a start time)
    const minimumLoadingTime = 333; // 1/3 second in milliseconds
    
    // Use setTimeout to ensure the spinner shows for at least the minimum time
    setTimeout(() => {
        isLoading.value = false;
    }, minimumLoadingTime);
};

// Create the map
const initMap = () => {
    startLoading();
    
    // Create the map instance
    map = L.map('leaflet-map', {
        center: mapConfig.center,
        zoom: mapConfig.zoom,
        maxZoom: mapConfig.maxZoom,
        minZoom: mapConfig.minZoom,
        zoomControl: true,
        scrollWheelZoom: false,
        zoomAnimation: true,
        fadeAnimation: true
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
    map.on('moveend', onMapMoved);
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
    console.log('Map initialized');
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
    
    markerCount.value = validEvents.length;
    
    // Create markers immediately (no setTimeout)
    validEvents.forEach(event => {
        const latLng = getLatLng(event);
        if (!latLng) return;
        
        const marker = L.marker(latLng, {
            icon: createMarkerIcon(event)
        });
        
        // Store event data with marker
        marker.eventData = event;
        
        // Add click handler
        marker.on('click', () => {
            selectedMarker.value = event.id;
            
            // Update marker styles
            updateMarkerStyles();
            
            // Create popup
            createPopupForMarker(marker);
            
            // Emit marker click event
            emit('markerClick', event);
        });
        
        // Add to cluster group
        markerClusterGroup.addLayer(marker);
        markers.push(marker);
    });
    
    console.log(`Created ${markers.length} markers`);
    
    // Stop loading with the minimum display time
    stopLoading();
};

// Update marker styles
const updateMarkerStyles = () => {
    markers.forEach(marker => {
        marker.setIcon(createMarkerIcon(marker.eventData));
    });
};

// Create popup for a marker
const createPopupForMarker = (marker) => {
    if (!marker.eventData) return;
    
    const event = marker.eventData;
    
    // Create popup
    const popup = L.popup({
        className: 'custom-popup',
        closeButton: false,
        maxWidth: 300,
        minWidth: 300,
        autoPan: true,
        autoPanPadding: [50, 50]
    });
    
    // Create container for Vue component
    const container = document.createElement('div');
    
    // Set popup content
    popup.setContent(container);
    
    // Bind popup to marker and open it
    marker.bindPopup(popup).openPopup();
    
    // Mount Vue component to container
    nextTick(() => {
        const app = createApp(MapElement, { data: event });
        app.mount(container);
    });
};

// Map moved event handler
const onMapMoved = () => {
    if (!map) return;
    
    const bounds = map.getBounds();
    const center = map.getCenter();
    
    if (bounds && center) {
        // When bounds change, we might need to fetch new data
        // but we'll let the parent component handle that
        MapStore.boundsUpdate(bounds, center);
        
        // We only want to show loading if we're actually fetching new data
        // so we're not setting loading state here
    }
};

// Toggle Map Method
const toggleMap = () => {
    emit('update:modelValue', !props.modelValue);
    nextTick(() => {
        if (map) {
            map.invalidateSize();
            onMapMoved();
        }
    });
};

// Initialize the map on component mount
onMounted(() => {
    // Use a short delay to ensure the DOM is ready
    setTimeout(() => {
        initMap();
    }, 0);
});

// Watch for events changes should trigger loading
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
        map.off('moveend', onMapMoved);
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
.search__map { @apply relative h-full; }
.leaflet-container { @apply !w-full !h-full !absolute !inset-0; }

/* Custom popup styles */
.leaflet-popup-content-wrapper { @apply !p-0 !rounded-2xl; }
.leaflet-popup-close-button { display: none; }
.leaflet-popup-content {
    @apply !w-[300px] !m-0;
    margin: 0;
    border-radius: 1rem;
    overflow: hidden;
}

.custom-popup {
    padding: 0;
}

/* Custom control styles */
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

/* Shadow utility */
.shadow-custom-2 {
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
}
</style>