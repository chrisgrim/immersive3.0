<script>
import { ref, reactive } from 'vue';

class SearchStore {
    constructor() {
        this.state = reactive({
            events: {
                data: [],
                total: 0,
                current_page: 1,
                per_page: 20,
                from: null,
                to: null,
                last_page: 1
            },
            location: {
                city: null,
                lat: null,
                lng: null,
                searchType: null,
                live: false
            },
            filters: {
                categories: [],
                tags: [],
                price: [0, 1000]
            },
            maxPrice: 1000,
            loading: false
        });
        
        this.listeners = [];
    }

    // Simple subscription system
    subscribe(callback) {
        this.listeners.push(callback);
        callback(this.state); // Call immediately with current state
        return () => {
            this.listeners = this.listeners.filter(l => l !== callback);
        };
    }

    // Update state and notify listeners
    updateState(newData) {
        // Update events data
        if (newData.events) {
            this.state.events = {
                data: newData.events.data || [],
                total: newData.events.total || 0,
                current_page: newData.events.current_page || 1,
                per_page: newData.events.per_page || 20,
                from: newData.events.from,
                to: newData.events.to,
                last_page: newData.events.last_page || 1
            };
        }

        // Update location data
        if (newData.location) {
            this.state.location = {
                ...this.state.location,
                ...newData.location
            };
        }

        // Update filters
        if (newData.filters) {
            this.state.filters = {
                ...this.state.filters,
                ...newData.filters
            };
        }

        // Update maxPrice
        if (newData.maxPrice !== undefined) {
            this.state.maxPrice = newData.maxPrice;
        }

        // Notify listeners
        this.listeners.forEach(listener => listener(this.state));
    }

    setLoading(isLoading) {
        this.state.loading = isLoading;
        this.listeners.forEach(listener => listener(this.state));
    }
}

// Export a single instance
export default new SearchStore();
</script> 