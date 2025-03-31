<script>
import { ref, reactive } from 'vue';
import axios from 'axios';

class SearchStore {
    constructor() {
        // Create the state from scratch
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
            filters: {
                categories: [],
                tags: [],
                price: [0, 1000],
                searchedMaxPrice: 1000
            },
            location: {
                city: null,
                lat: null,
                lng: null,
                searchType: null,
                live: false
            },
            dates: {
                start: null,
                end: null
            },
            maxPrice: 1000,
            loading: false
        });
        
        this.listeners = [];
    }
    
    // Initialize state from URL and props
    initializeFromUrl(searchedEvents = {}, defaultMaxPrice = 1000) {
        const params = new URLSearchParams(window.location.search);
        
        const initialState = {
            events: {
                data: searchedEvents?.data || [],
                total: searchedEvents?.total || 0,
                current_page: searchedEvents?.current_page || 1,
                last_page: searchedEvents?.last_page || 1,
                from: searchedEvents?.from || null,
                to: searchedEvents?.to || null,
                per_page: searchedEvents?.per_page || 15
            },
            location: {
                city: params.get('city') || null,
                lat: params.has('lat') ? parseFloat(params.get('lat')) : null,
                lng: params.has('lng') ? parseFloat(params.get('lng')) : null,
                searchType: params.get('searchType') || null,
                live: params.get('live') === 'true'
            },
            dates: {
                start: params.get('start') || null,
                end: params.get('end') || null
            },
            filters: {
                categories: params.has('category') ? params.get('category').split(',').map(Number) : [],
                tags: params.has('tag') ? params.get('tag').split(',').map(Number) : [],
                price: [
                    parseInt(params.get('price0')) || 0,
                    parseInt(params.get('price1')) || defaultMaxPrice
                ],
                searchedMaxPrice: defaultMaxPrice
            },
            maxPrice: defaultMaxPrice
        };
        
        this.updateState(initialState);
        return initialState;
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

        // For location data, handle null values explicitly
        if (newData.location) {
            // Create a new object to ensure reactivity
            const location = {
                city: newData.location.city !== undefined ? newData.location.city : this.state.location.city,
                lat: newData.location.lat !== undefined ? newData.location.lat : this.state.location.lat,
                lng: newData.location.lng !== undefined ? newData.location.lng : this.state.location.lng,
                searchType: newData.location.searchType !== undefined ? newData.location.searchType : this.state.location.searchType,
                live: newData.location.live !== undefined ? newData.location.live : this.state.location.live
            };
            
            // Replace the entire object
            this.state.location = location;
        }

        // For dates, handle null values explicitly
        if (newData.dates) {
            this.state.dates = {
                start: newData.dates.start !== undefined ? newData.dates.start : this.state.dates.start,
                end: newData.dates.end !== undefined ? newData.dates.end : this.state.dates.end
            };
        }

        // Update filters
        if (newData.filters) {
            this.state.filters = {
                ...this.state.filters,
                ...newData.filters
            };
        }

        // Update maxPrice when explicitly provided or based on searchType
        if (newData.maxPrice !== undefined || 
            (newData.location && newData.location.searchType !== 'inPerson')) {
            this.state.maxPrice = newData.maxPrice;
            this.state.filters.searchedMaxPrice = newData.maxPrice;
        }

        // Notify listeners
        this.listeners.forEach(listener => listener(this.state));
    }

    setLoading(isLoading) {
        this.state.loading = isLoading;
        this.listeners.forEach(listener => listener(this.state));
    }

    async fetchResults(queryString) {
        try {
            this.setLoading(true);
            const response = await axios.get(`/api/index/search?${queryString}`);
            
            // Get searchType from URL
            const params = new URLSearchParams(window.location.search);
            
            // Update the store with ALL necessary data
            this.updateState({
                events: {
                    current_page: response.data.current_page,
                    data: response.data.data,
                    from: response.data.from,
                    last_page: response.data.last_page,
                    per_page: response.data.per_page,
                    to: response.data.to,
                    total: response.data.total
                },
                location: {
                    city: response.data.city,
                    lat: response.data.lat,
                    lng: response.data.lng,
                    searchType: params.get('searchType'),
                    live: response.data.live
                },
                filters: {
                    ...this.state.filters,
                    searchedMaxPrice: response.data.maxPrice
                },
                maxPrice: response.data.maxPrice
            });
            
            return response.data;
        } catch (error) {
            console.error('Error fetching results:', error);
            throw error;
        } finally {
            this.setLoading(false);
        }
    }
}

// Export a single instance
export default new SearchStore();
</script> 