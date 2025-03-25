<script>
import { ref, reactive } from 'vue';

const instance = ref(null);

class EventStore {
    constructor() {
        if (instance.value) {
            return instance.value;
        }
        
        instance.value = this;
        this.instanceId = Math.random().toString(36).substring(7);
        
        this.isInitialLoad = true;
        this.isFirstFetch = true;
        this.isInitialized = false;
        this.listeners = [];
        this.isUpdating = false;
        this.lastFetchTimestamp = 0;
        this.boundaryUpdateTimer = null;
        this.originalMaxPrice = null;
        
        // Initialize state first
        this.state = reactive(this._createInitialState());
        
        // Set up event listeners
        window.addEventListener('popstate', () => {
            if (!this.isUpdating && this.isInitialized) {
                this.initializeFromUrl(true);
                this.notify();
            }
        });

        // Initialize from URL once
        this.initializeFromUrl(false);
        
        this.isInitialLoad = false;
        this.isInitialized = true;
    }

    _createInitialState() {
        const defaultMaxPrice = this.originalMaxPrice || 1000;  // Single source of truth
        return {
            source: 'initialSearch',
            location: {
                city: null,
                lat: null,
                lng: null,
                searchType: null,
                live: false,
                NElat: null,
                NElng: null,
                SWlat: null,
                SWlng: null
            },
            dates: {
                start: null,
                end: null
            },
            filters: {
                categories: [],
                tags: [],
                price: [0, defaultMaxPrice]  // Use same value
            },
            events: {
                data: [],
                total: 0,
                loading: false
            },
            maxPrice: defaultMaxPrice,  // Use same value
            page: 1
        };
    }

    initializeFromUrl(shouldFetch = false) {
        const params = new URLSearchParams(window.location.search);

        // Keep existing events if we have them
        const currentEvents = this.state.events;

        this.state = {
            source: 'initialSearch',
            location: {
                city: params.get('city') || null,
                lat: params.has('lat') ? parseFloat(params.get('lat')) : null,
                lng: params.has('lng') ? parseFloat(params.get('lng')) : null,
                searchType: params.get('searchType') || null,
                live: params.get('live') === 'true',
                NElat: params.has('NElat') ? parseFloat(params.get('NElat')) : null,
                NElng: params.has('NElng') ? parseFloat(params.get('NElng')) : null,
                SWlat: params.has('SWlat') ? parseFloat(params.get('SWlat')) : null,
                SWlng: params.has('SWlng') ? parseFloat(params.get('SWlng')) : null
            },
            dates: {
                start: params.get('start') || null,
                end: params.get('end') || null
            },
            filters: {
                categories: params.has('category') 
                    ? params.get('category').split(',').map(Number) 
                    : [],
                tags: params.has('tag') 
                    ? params.get('tag').split(',').map(Number) 
                    : [],
                price: [
                    parseInt(params.get('price0') || 0),
                    parseInt(params.get('price1') || this.state.maxPrice)
                ]
            },
            events: currentEvents.data?.length ? currentEvents : {  // Keep existing events if we have them
                data: [],
                total: 0,
                loading: false
            },
            maxPrice: this.state.maxPrice,
            page: params.get('page') ? parseInt(params.get('page')) : 1
        };
        
        if (shouldFetch) {
            this.fetchEvents();
        }
    }

    fetchEvents() {
        console.log('fetchEvents called with state:', {
            searchType: this.state.location.searchType,
            filters: this.state.filters,
            source: this.state.source,
            isFirstFetch: this.isFirstFetch
        });

        if (this.isFirstFetch && 
            this.state.location.searchType !== 'inPerson' && 
            this.state.location.searchType !== 'null') {
            console.log('First fetch and not in-person/null search, returning early');
            this.isFirstFetch = false;
            return Promise.resolve(this.state.events);
        }
        // Avoid fetching too frequently
        const now = Date.now();
        if (now - this.lastFetchTimestamp < 200) {
            return Promise.resolve(this.state.events);
        }
        this.lastFetchTimestamp = now;
        
        // Set loading state
        this.state.events.loading = true;
        this.notify();
        
        // Build URL parameters from the current state
        const params = new URLSearchParams(window.location.search);
        
        // CRITICAL FIX: Make sure searchType is correct in API request
        params.set('searchType', this.state.location.searchType || null);
        
        // Log the URL we're using
        console.log('Making API request with:', params.toString());

        // Make API request
        return fetch(`/api/index/search?${params.toString()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Update events in state - ensure we always set the data array
                this.state.events = {
                    data: data.data || [],  // This is good, keeps empty array
                    total: data.total || 0,
                    loading: false,
                    current_page: data.current_page || 1,
                    per_page: data.per_page || 20,
                    from: data.from,
                    to: data.to,
                    last_page: data.last_page || 1
                };

                // Update maxPrice if it exists in the response
                if (data.maxPrice !== undefined) {
                    this.state.maxPrice = data.maxPrice;
                }

                // Use the same params object we created above
                this.state.source = params.get('live') === 'true' ? 'eventStore' : 'initialSearch';

                console.log('After api call Events updated with:', this.state.source);
                
                // Notify subscribers
                this.notify();
                return this.state.events;
            })
            .catch(error => {
                console.error('Error fetching events:', error);
                this.state.events.loading = false;
                this.notify();
                throw error;
            });
    }

    subscribe(callback) {
        this.listeners.push(callback);
        
        // Call immediately with current state, but don't reinitialize
        if (this.isInitialized) {
            callback(this.state);
        }
        
        // Return unsubscribe function
        return () => {
            this.listeners = this.listeners.filter(listener => listener !== callback);
        };
    }

    notify() {
        this.listeners.forEach(listener => {
            try {
                listener(this.state);
            } catch (e) {
                console.error('Error in EventStore listener:', e);
            }
        });
    }
    
    changePage(page) {
        this.update({ page }, true);
    }
    
    setSearchType(searchType) {
        this.update({
            location: {
                searchType: searchType
            }
        }, true);
    }

    update(changes, fetchData = false, debounce = false) {
        console.log('EventStore update called with:', {
            changes,
            fetchData,
            debounce,
            currentState: {
                searchType: this.state.location.searchType,
                filters: this.state.filters,
                source: this.state.source
            }
        });

        if (this.isUpdating) {
            console.log('Update already in progress, returning');
            return;
        }
        this.isUpdating = true;
        
        // Check if we're updating boundaries
        const isBoundaryUpdate = changes.location && (
            changes.location.NElat !== undefined || 
            changes.location.NElng !== undefined ||
            changes.location.SWlat !== undefined ||
            changes.location.SWlng !== undefined
        );
        
        // If it's a boundary update and debounce is requested, use debounce logic
        if (isBoundaryUpdate && debounce) {
            // Clear any existing timer
            if (this.boundaryUpdateTimer) {
                clearTimeout(this.boundaryUpdateTimer);
            }
            
            // Set a new timer
            this.boundaryUpdateTimer = setTimeout(() => {
                this._completeUpdate(changes, fetchData);
            }, 500);
            
            this.isUpdating = false;
            return;
        }
        
        // For non-boundary updates or when debounce is not requested
        this._completeUpdate(changes, fetchData);
    }

    _completeUpdate(changes, fetchData) {
        console.log('_completeUpdate starting with:', {
            changes,
            fetchData,
            currentState: {
                searchType: this.state.location.searchType,
                filters: this.state.filters
            }
        });

        const params = new URLSearchParams(window.location.search);

        // Deep merge changes with current state
        if (changes.location) {
            // IMPORTANT: Preserve searchType if not explicitly changed
            if (!changes.location.searchType) {
                changes.location.searchType = this.state.location.searchType || null;
            }
            
            this.state.location = { ...this.state.location, ...changes.location };
        }
        if (changes.dates) {
            this.state.dates = { ...this.state.dates, ...changes.dates };
        }
        if (changes.filters) {
            // Handle each filter type
            if (changes.filters.categories !== undefined) {
                this.state.filters.categories = changes.filters.categories;
            }
            if (changes.filters.tags !== undefined) {
                this.state.filters.tags = changes.filters.tags;
            }
            if (changes.filters.price !== undefined) {
                this.state.filters.price = changes.filters.price;
            }
        }
        if (changes.page !== undefined) {
            this.state.page = changes.page;
        }
        console.log('in complete update method the source is', changes.source);
        // Update URL to reflect new state
        this.updateUrl();
        
        // Notify subscribers
        this.notify();
        
        // After state updates
        console.log('State after updates:', {
            location: this.state.location,
            filters: this.state.filters,
            source: this.state.source
        });

        // BEFORE fetch
        if (fetchData && !this.isInitialLoad) {
            console.log('About to fetch events with state:', {
                searchType: this.state.location.searchType,
                filters: this.state.filters,
                source: this.state.source
            });
            this.fetchEvents();
        }
        
        // Reset updating flag after a small delay
        setTimeout(() => {
            this.isUpdating = false;
        }, 50);
    }

    updateUrl() {
        const params = new URLSearchParams();
        
        // Add location parameters
        if (this.state.location.city) {
            params.set('city', this.state.location.city);
        }
        if (this.state.location.lat) {
            params.set('lat', this.state.location.lat.toString());
        }
        if (this.state.location.lng) {
            params.set('lng', this.state.location.lng.toString());
        }
        
        // CRITICAL FIX: Always include searchType in URL
        params.set('searchType', this.state.location.searchType || null);
        
        // CRITICAL FIX: Always include 'live' parameter when searchType is inPerson
        if (this.state.location.searchType === 'inPerson') {
            params.set('live', this.state.location.live ? 'true' : 'false');
        }
        
        // Add boundary parameters if in live mode
        if (this.state.location.live) {
            if (this.state.location.NElat) params.set('NElat', this.state.location.NElat.toString());
            if (this.state.location.NElng) params.set('NElng', this.state.location.NElng.toString());
            if (this.state.location.SWlat) params.set('SWlat', this.state.location.SWlat.toString());
            if (this.state.location.SWlng) params.set('SWlng', this.state.location.SWlng.toString());
        }
        
        // Add date parameters
        if (this.state.dates.start) {
            params.set('start', this.state.dates.start);
        }
        if (this.state.dates.end) {
            params.set('end', this.state.dates.end);
        }
        
        // Add filter parameters
        if (this.state.filters.categories.length > 0) {
            params.set('category', this.state.filters.categories.join(','));
        }
        if (this.state.filters.tags.length > 0) {
            params.set('tag', this.state.filters.tags.join(','));
        }
        
        // Add price parameters
        if (this.state.filters.price[0] > 0) {
            params.set('price0', this.state.filters.price[0].toString());
        }
        if (this.state.filters.price[1] !== null) {
            params.set('price1', this.state.filters.price[1].toString());
        }
        
        // Add pagination
        if (this.state.page > 1) {
            params.set('page', this.state.page.toString());
        }
        
        // Update the URL
        const newUrl = `${window.location.pathname}?${params.toString()}`;
        window.history.replaceState(
            { source: 'eventStore' }, 
            document.title,
            newUrl
        );
    }

    setSource(source) {
        this.state.source = source;
        this.notify();
    }

    setInitialData(maxPrice, events) {
        console.log('setting initial data', maxPrice, events);
        if (!this.originalMaxPrice) {
            this.originalMaxPrice = maxPrice;
            // Recreate the state with the correct maxPrice
            this.state = reactive(this._createInitialState());
            this.state.maxPrice = maxPrice;
            this.state.events = events;
            // Now that we have the correct maxPrice, we can initialize from URL
            this.initializeFromUrl(false);
            this.isInitialized = true;
            // Notify subscribers of the new state
            this.notify();
        }
        console.log('initial data set', this.state.maxPrice, this.state.events);
    }
}

// Create and export the store instance
export default new EventStore();
</script>
