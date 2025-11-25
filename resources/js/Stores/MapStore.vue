<script>
import { ref } from 'vue';

// Create store instance outside of component scope
const state = ref({
    bounds: {
        northEast: { lat: null, lng: null },
        southWest: { lat: null, lng: null },
        center: [null, null]
    },
    zoom: null,
    lastUpdate: null
});

const subscribers = [];

const MapStore = {
    state,
    
    boundsUpdate(bounds, center) {
        // Validate that all coordinates are valid numbers
        const neLat = parseFloat(bounds._northEast?.lat);
        const neLng = parseFloat(bounds._northEast?.lng);
        const swLat = parseFloat(bounds._southWest?.lat);
        const swLng = parseFloat(bounds._southWest?.lng);
        const centerLat = parseFloat(center?.lat);
        const centerLng = parseFloat(center?.lng);
        
        // If any coordinate is NaN or invalid, log warning and skip update
        if (isNaN(neLat) || isNaN(neLng) || isNaN(swLat) || isNaN(swLng) || isNaN(centerLat) || isNaN(centerLng)) {
            console.warn('MapStore: Invalid bounds data received, skipping update', {
                bounds,
                center,
                parsed: { neLat, neLng, swLat, swLng, centerLat, centerLng }
            });
            return;
        }
        
        const updatedBounds = {
            northEast: {
                lat: neLat,
                lng: neLng
            },
            southWest: {
                lat: swLat,
                lng: swLng
            },
            center: [centerLat, centerLng]
        };
        
        state.value.bounds = updatedBounds;
        
        // Notify subscribers
        subscribers.forEach(callback => callback(state.value));
    },

    subscribe(callback) {
        subscribers.push(callback);
        return () => {
            const index = subscribers.indexOf(callback);
            if (index > -1) subscribers.splice(index, 1);
        };
    }
};

export default MapStore;
</script> 