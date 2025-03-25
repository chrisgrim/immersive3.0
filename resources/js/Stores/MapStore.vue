<script>
import { ref } from 'vue';

// Create store instance outside of component scope
const state = ref({
    bounds: {
        northEast: { lat: null, lng: null },
        southWest: { lat: null, lng: null },
        center: [null, null]
    }
});

const subscribers = [];

const MapStore = {
    state,
    
    boundsUpdate(bounds) {
        const updatedBounds = {
            northEast: {
                lat: bounds._northEast.lat,
                lng: bounds._northEast.lng
            },
            southWest: {
                lat: bounds._southWest.lat,
                lng: bounds._southWest.lng
            },
            center: [
                (bounds._northEast.lat + bounds._southWest.lat) / 2,
                (bounds._northEast.lng + bounds._southWest.lng) / 2
            ]
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