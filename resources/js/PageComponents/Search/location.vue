<template>
    <div class="event-search relative min-h-[calc(100vh-7rem)]">
        <div class="w-full flex">
            <!-- Events List Section -->
            <section 
                :class="{ 'w-0 hidden' : searchData.location.fullMap }"
                class="z-10 relative inline-block w-[59%] min-h-[calc(100vh-8rem)]"
            >
                <div class="inline-block text-left pt-16 pb-4 px-8">
                    <p v-if="hasEvents">{{ events.total }} immersive events.</p>
                    <p v-else>There are no location based events in {{ searchData.location.name }} with these filters.</p>
                </div>
                
                <div class="px-8">
                    <EventList
                        v-if="hasEvents"
                        :items="events.data"
                        :user="user"
                        :columns="4"
                    />
                    <Pagination 
                        v-if="events"
                        class="mt-6"
                        :pagination="events"
                        @paginate="handlePageChange"
                    />
                </div>
            </section>

            <!-- Map Component -->
            <Map
                v-model="searchData"
                :key="mapKey"
                :events="events.data"
                @submit="onSubmit"
                @fullMap="fullMap"
            />
        </div>
        
        <!-- Add ref to Nav component if it's used in this component -->
        <Nav ref="childNav" v-if="false" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import axios from 'axios'
import Nav from './Components/nav.vue'
import EventList from '@/GlobalComponents/Grid/event-grid.vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import Map from './Components/map.vue'
import eventStore from '@/Stores/EventStore'

// Constants
const DEFAULT_LOCATION = {
    name: 'Search by City',
    fullMap: false,
    live: false,
    zoom: 13,
    center: [40.7127753, -74.0059728],
    mapboundary: null
}

const DEFAULT_FILTERS = {
    categories: [],
    tags: [],
    price: { min: 0, max: 100 },
    dates: { start: null, end: null }
}

// Props
const props = defineProps({
    searchedEvents: Object,
    categories: Array,
    user: Object,
    tags: Array,
    mobile: Boolean,
    searchedCategories: Array,
    searchedTags: Array,
    inPersonCategories: Array,
    maxPrice: {
        type: Number,
        required: true
    }
})

// Refs & State
const childNav = ref(null)
const mapKey = ref(0)
const events = ref({
    data: props.searchedEvents?.data || [],
    total: props.searchedEvents?.total || 0
})
const searchData = ref({ location: { ...DEFAULT_LOCATION } })
const activeFilters = ref({ ...DEFAULT_FILTERS })
const unsubscribe = ref(null)
const isInitialLoad = ref(true)

// Computed
const hasEvents = computed(() => events.value.data && events.value.data.length)

// URL Parameter Handling
const buildSearchParams = (type, value) => {
    console.log('buildSearchParams called with:', { type, value });
    const currentParams = new URLSearchParams(window.location.search)
    const params = new URLSearchParams()

    // Copy location params
    const locationParams = ['searchType', 'lat', 'lng', 'live', 'NElat', 'NElng', 'SWlat', 'SWlng', 'city']
    locationParams.forEach(param => {
        if (currentParams.has(param)) params.set(param, currentParams.get(param))
    })
    
    // Handle different filter types
    if (type === 'location') {
        Object.entries(value).forEach(([key, val]) => params.set(key, val))
    }
    if (activeFilters.value.categories.length) {
        params.set('category', activeFilters.value.categories.join(','))
    }
    if (activeFilters.value.tag) {
        params.set('tag', activeFilters.value.tag)
    }
    if (type === 'price') {
        params.set('price0', value[0])
        params.set('price1', value[1])
    }
    if (activeFilters.value.dates.start || activeFilters.value.dates.end) {
        params.set('start', activeFilters.value.dates.start)
        params.set('end', activeFilters.value.dates.end)
    }

    return params
}

// Filter Handling
const handleFilterUpdate = async (event) => {
    console.log('handleFilterUpdate called with:', event);
    
    // CRITICAL FIX: Add debounce flag to prevent rapid successive calls
    if (window._isProcessingFilterUpdate) {
        console.log('Already processing a filter update, skipping...');
        return;
    }
    
    // Set processing flag
    window._isProcessingFilterUpdate = true;
    
    const { type, value } = event.detail;
    
    try {
        // Track what's changing to avoid unnecessary updates
        let hasUpdated = false;
        
        if (type === 'price') {
            // Only update if different
            if (activeFilters.value.price.min !== value[0] || 
                activeFilters.value.price.max !== value[1]) {
                activeFilters.value.price = { min: value[0], max: value[1] };
                hasUpdated = true;
            }
        } else if (type === 'location') {
            // Ensure we have valid coordinates
            const lat = value.lat || 40.7127753; // Default to New York if missing
            const lng = value.lng || -74.0059728;
            
            // Check if location actually changed
            const currentCenter = searchData.value.location.center || [];
            const locationChanged = 
                currentCenter[0] !== lat || 
                currentCenter[1] !== lng ||
                searchData.value.location.name !== value.city;
            
            if (locationChanged) {
                searchData.value.location = {
                    ...searchData.value.location,
                    name: value.city,
                    center: [lat, lng],
                    live: false,
                    zoom: 13
                };
                hasUpdated = true;
            }
            
            // Update date filters if they exist in the location update
            if ((value.startDate || value.endDate) && 
                (activeFilters.value.dates.start !== value.startDate || 
                 activeFilters.value.dates.end !== value.endDate)) {
                activeFilters.value.dates = {
                    start: value.startDate,
                    end: value.endDate
                };
                hasUpdated = true;
            }
        } else {
            // For other filters (categories, tags, etc.)
            const currentValue = activeFilters.value[type === 'category' ? 'categories' : type];
            // Simple comparison may not work for arrays - using JSON.stringify
            if (JSON.stringify(currentValue) !== JSON.stringify(value)) {
                activeFilters.value[type === 'category' ? 'categories' : type] = value;
                hasUpdated = true;
            }
        }
        
        // Only proceed if something actually changed
        if (hasUpdated) {
            const params = buildSearchParams(type, value);
            
            // Make sure lat/lng are valid in params
            if (!params.has('lat') || !params.has('lng') || 
                params.get('lat') === 'undefined' || params.get('lng') === 'undefined') {
                params.set('lat', '40.7127753');
                params.set('lng', '-74.0059728');
            }
            
            // Check if 'start' and 'end' parameters should be included
            if (activeFilters.value.dates.start && !params.has('start')) {
                params.set('start', activeFilters.value.dates.start);
            }
            if (activeFilters.value.dates.end && !params.has('end')) {
                params.set('end', activeFilters.value.dates.end);
            }
            
            // Update URL without triggering another filter-update event
            const currentUrl = window.location.pathname + '?' + params.toString();
            if (window.location.href !== currentUrl) {
                // Use replaceState to avoid adding to browser history
                window.history.replaceState(
                    { preventFilterUpdate: true }, // Add a state object with a flag
                    '', 
                    currentUrl
                );
            }
            
            // Fetch updated data from API
            const response = await axios.get(`/api/index/search?${params.toString()}`);
            events.value = response.data;
        } else {
            console.log('No actual changes detected, skipping update');
        }
    } catch (error) {
        console.error('Error applying filters:', error);
    } finally {
        // Clear the processing flag after a delay
        setTimeout(() => {
            window._isProcessingFilterUpdate = false;
        }, 300);
    }
};

// Initialization Methods
const initializeFiltersFromUrl = () => {
    const params = new URLSearchParams(window.location.search)
    
    if (params.has('category')) {
        activeFilters.value.categories = params.get('category').split(',')
    }
    if (params.has('tags')) {
        activeFilters.value.tags = params.get('tags').split(',')
    }
    if (params.has('price0') || params.has('price1')) {
        activeFilters.value.price = [
            parseInt(params.get('price0')) || 0,
            parseInt(params.get('price1')) || 670
        ]
    }
    if (params.has('start') || params.has('end')) {
        activeFilters.value.dates = {
            start: params.get('start'),
            end: params.get('end')
        }
    }
}

const initializeLocationFromUrl = () => {
    const params = new URLSearchParams(window.location.search)
    
    if (params.has('lat') && params.has('lng')) {
        searchData.value.location = {
            ...searchData.value.location,
            name: params.get('city') || 'Search by City',
            center: [parseFloat(params.get('lat')), parseFloat(params.get('lng'))],
            live: params.get('live') === 'true'
        }
    }
}

// Event Handlers
const onSubmit = () => {
    // Just update the store with current map boundaries if needed
    if (searchData.value.location.mapboundary) {
        eventStore.update({
            location: {
                NElat: searchData.value.location.mapboundary.northEast.lat,
                NElng: searchData.value.location.mapboundary.northEast.lng,
                SWlat: searchData.value.location.mapboundary.southWest.lat,
                SWlng: searchData.value.location.mapboundary.southWest.lng,
                // Include other location data to be sure
                city: searchData.value.location.name,
                lat: searchData.value.location.center[0],
                lng: searchData.value.location.center[1],
                live: searchData.value.location.live
            }
        });
    }
};

const updateEvents = (value) => events.value = value
const fullMap = () => mapKey.value += 1

const handlePageChange = (page) => {
    eventStore.changePage(page);
    window.scrollTo(0, 0);
}

const clear = () => {
    searchData.value = { location: { ...DEFAULT_LOCATION } }
}

const handleCategoryFilter = async (categoryId) => {
    // Update the EventStore with the new category
    eventStore.update({
        filters: {
            categories: [categoryId]
        }
    });
}

// Lifecycle
onMounted(() => {
    // Initialize store with any URL parameters
    eventStore.initializeFromUrl();
    
    // Subscribe to changes from the EventStore
    unsubscribe.value = eventStore.subscribe(state => {
        // Don't update if this is our own change
        if (eventStore.isUpdating && !isInitialLoad.value) return;
        
        // Update events data from store
        if (state.events.data && state.events.data.length) {
            events.value = state.events;
        }
        
        // Update location data
        if (state.location) {
            searchData.value.location = {
                ...searchData.value.location,
                name: state.location.city || 'Search by City',
                center: [state.location.lat || 40.7127753, state.location.lng || -74.0059728],
                live: state.location.live || false,
                zoom: 13 // Default zoom level
            };
        }
        
        // No longer initial load
        isInitialLoad.value = false;
    });
    
    // Fetch events on initial mount
    if (window.location.pathname === '/index/search') {
        eventStore.fetchEvents();
    }
    
    // Send maxPrice to any listeners
    window.dispatchEvent(new CustomEvent('max-price-update', { detail: props.maxPrice }));
})

onUnmounted(() => {
    if (unsubscribe.value) {
        unsubscribe.value();
    }
})

// No need for URL watchers - store handles all URL interactions
</script>