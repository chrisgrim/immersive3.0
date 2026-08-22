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
                price: [0, null],
                maxPrice: null,
                searchingByPrice: false,
                atHome: false,
                // null, a raw slug string (hydrated from the URL, not yet
                // resolved to a display name), or { slug, name } once
                // resolved — see at-home-search.vue's onMounted() and
                // nav-search.vue's handleAtHomeSearch().
                remoteLocation: null
            },
            location: {
                city: null,
                lat: null,
                lng: null,
                live: false
            },
            dates: {
                start: null,
                end: null
            },
            loading: false
        });
        
        this.listeners = new Set();
    }
    
    // Initialize state from URL and props
    initializeFromUrl(searchedEvents = {}, maxPrice = null) {
        const params = new URLSearchParams(window.location.search);
        
        // Format city name to remove country for US cities
        let cityName = params.get('city');
        if (cityName && cityName.endsWith(", USA")) {
            cityName = cityName.replace(", USA", "");
        }
        
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
                city: cityName || null,
                lat: params.has('lat') ? parseFloat(params.get('lat')) : null,
                lng: params.has('lng') ? parseFloat(params.get('lng')) : null,
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
                    params.has('price1') ? parseInt(params.get('price1')) : maxPrice || null
                ],
                maxPrice: maxPrice || null,
                searchingByPrice: params.has('price0') || params.has('price1'),
                atHome: params.get('searchType') === 'atHome',
                // Raw slug by default — at-home-search.vue's onMounted()
                // resolves it to { slug, name } once it has the display
                // name. But nav-search.vue's own onMounted (which calls this)
                // fires AFTER its child at-home-search.vue's onMounted (Vue
                // mounts children before their parent), so if that child
                // already resolved it — e.g. synchronously from a
                // server-provided initial-remote-location prop — this must
                // not clobber that object back down to the raw string, or
                // active-filters.vue's label falls back to the generic
                // "Remote Events" right after briefly showing the real name.
                remoteLocation: (() => {
                    const slug = params.get('remoteLocation') || null;
                    const current = this.state.filters.remoteLocation;
                    return (current && typeof current === 'object' && current.slug === slug)
                        ? current
                        : slug;
                })()
            },
        };

        this.updateState(initialState);
        return initialState;
    }

    // Simple subscription system — Set dedupes so a re-mount before unmount
    // can't accumulate duplicate listeners.
    subscribe(callback) {
        this.listeners.add(callback);
        callback(this.state);
        return () => {
            this.listeners.delete(callback);
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

        // Only update maxPrice if it's explicitly provided and not undefined
        if (newData.maxPrice !== undefined && newData.maxPrice !== null) {
            this.state.filters.maxPrice = newData.maxPrice;
        } else if (newData.filters?.atHome === true && this.state.filters.maxPrice === null) {
            // Only update for atHome searches if we don't already have a maxPrice
            this.state.filters.maxPrice = newData.maxPrice;
        }

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
            
            // Format city name to remove country for US cities
            let cityName = response.data.city;
            if (cityName && cityName.endsWith(", USA")) {
                cityName = cityName.replace(", USA", "");
            }
            
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
                    city: cityName,
                    lat: response.data.lat,
                    lng: response.data.lng,
                    live: response.data.live
                },
                filters: {
                    ...this.state.filters,
                    ...(this.state.filters.searchingByPrice 
                        ? {
                            maxPrice: Math.max(this.state.filters.price[1], response.data.maxPrice)
                        } 
                        : {
                            price: [0, response.data.maxPrice],
                            maxPrice: response.data.maxPrice
                        })
                },
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