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
let lastBoundsKey = null;

const MapStore = {
    state,
    
    boundsUpdate(bounds, center, zoom = null, { force = false } = {}) {
        // Validate that all coordinates are valid numbers
        const neLat = parseFloat(bounds?._northEast?.lat);
        let neLng = parseFloat(bounds?._northEast?.lng);
        const swLat = parseFloat(bounds?._southWest?.lat);
        let swLng = parseFloat(bounds?._southWest?.lng);
        const centerLat = parseFloat(center?.lat);
        let centerLng = parseFloat(center?.lng);
        
        // If any coordinate is NaN or invalid, log warning and skip update
        if (isNaN(neLat) || isNaN(neLng) || isNaN(swLat) || isNaN(swLng) || isNaN(centerLat) || isNaN(centerLng)) {
            console.warn('MapStore: Invalid bounds data received, skipping update', {
                bounds,
                center,
                parsed: { neLat, neLng, swLat, swLng, centerLat, centerLng }
            });
            return;
        }
        
        // Leaflet's edges go past ±180 when the map is zoomed out across the
        // date line (-211 for the United States on a wide screen), which the
        // server refuses, dropping the area filter altogether. Wrap them into
        // range; a view a whole world wide searches the whole world. A box
        // that crosses the line then ends east of where it starts, which the
        // search understands.
        const wrap = (lng) => (lng >= -180 && lng <= 180 ? lng : ((((lng + 180) % 360) + 360) % 360) - 180);
        if (neLng - swLng >= 360) {
            swLng = -180;
            neLng = 180;
        } else {
            swLng = wrap(swLng);
            neLng = wrap(neLng);
        }
        centerLng = wrap(centerLng);

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

        // Leaflet fires moveend for a size change too (invalidateSize when a
        // mobile browser's toolbar collapses, the desktop full-map toggle).
        // The same rectangle is not a new search: it would reset the list
        // to page 1 and rebuild every marker for nothing.
        // Keyed on centre and zoom when known: a size change keeps both but
        // shows a bigger or smaller rectangle, and is still not a move.
        // `force` is a deliberate re-search of the view (full-map toggle).
        const key = Number.isFinite(zoom)
            ? JSON.stringify([centerLat.toFixed(5), centerLng.toFixed(5), zoom])
            : JSON.stringify(updatedBounds);
        if (key === lastBoundsKey && !force) return;
        lastBoundsKey = key;
        if (this.remembering) return;

        state.value.bounds = updatedBounds;
        
        // Notify subscribers
        subscribers.forEach(callback => callback(state.value));
    },

    /**
     * Note the view the page opened on, without searching: a saved box or a
     * country search is fitted with the move listener off, so a later size
     * change of the same view (phone toolbar, full-map toggle) is not taken
     * for a drag that replaces the search.
     */
    remember(bounds, center, zoom = null) {
        this.remembering = true;
        try {
            this.boundsUpdate(bounds, center, zoom);
        } finally {
            this.remembering = false;
        }
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