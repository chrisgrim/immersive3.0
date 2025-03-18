<template>
    <div class="event-search relative">
        <div class="w-full md:flex">
            <!-- Map Component (Background) -->
            <div class="fixed top-0 left-0 right-0 bottom-[40vh] transition-all duration-300 ease-in-out"
                 :class="searchData.location.fullMap ? 'z-[49]' : 'z-[48]'">
                <Map
                    v-model="searchData"
                    :key="mapKey"
                    :events="events.data"
                    :full-map="searchData.location.fullMap"
                    @submit="onSubmit"
                    @click="handleMapClick"
                />
            </div>

            <div 
                :style="{ height: `${mapHeight}px` }"
                @click="showFullMap"
                class="w-full relative z-[49] transition-all duration-300"
                :class="searchData.location.fullMap ? 'opacity-0 pointer-events-none' : ''" />

            <!-- Events List Section -->
            <div 
                :style="{
                    transform: searchData.location.fullMap 
                        ? `translate3d(0, ${mapHeight + 24 }px, 0)` 
                        : 'translate3d(0, 0px, 0)'
                }"
                class="min-h-[64vh] relative w-full z-49 transition-transform duration-300">
                <div class="bg-white rounded-t-5xl shadow-custom-6">
                    <div 
                        @click="hideFullMap"
                        class="w-full flex items-center gap-4 pt-4 h-28 relative before:content-[''] before:block before:absolute before:top-6 before:left-1/2 before:-translate-x-5 before:w-16 before:h-2 before:rounded-full before:bg-[#351b1b7a]">
                        <p class="text-black text-1xl font-medium w-full text-center" v-if="hasEvents">{{ events.total }} immersive events.</p>
                        <p class="text-black text-1xl font-medium w-full text-center" v-else>No events found in {{ searchData.location.name }}.</p>
                    </div>
                    <div class="whitespace-nowrap p-8 mt-[-1rem] overflow-y-hidden overflow-x-auto gap-x-6 scrolling-touch min-h-[64vh]">
                        <EventList
                        v-if="hasEvents"
                        :items="events.data"
                        :user="user"
                        :columns="2"
                    />
                    <Pagination 
                        v-if="events"
                        class="mt-6 mb-8"
                        :pagination="events"
                        @paginate="handlePageChange"
                    />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import axios from 'axios'
import Nav from './Components/nav.vue'
import EventList from '@/GlobalComponents/Grid/event-grid.vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import Map from './Components/map-mobile.vue'
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
const userMovedMap = ref(false)

// New refs for mobile interaction
const listContainer = ref(null)
const isMapFocused = ref(false)
const listPosition = ref(0)
const touchStart = ref(null)
const initialPosition = ref(null)

// Add new ref for expanded state
const isExpanded = ref(false)
const scrollHeight = ref(0)

// Add ref for map height
const mapHeight = ref(0)

// Add this near the other refs
const lastSearchCity = ref('');

// Computed
const hasEvents = computed(() => events.value.data && events.value.data.length)

// URL Parameter Handling
const buildSearchParams = (type, value) => {
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
const handleFilterUpdate = (event) => {
    const { type, value } = event.detail
    
    // Update the EventStore based on filter type
    if (type === 'price') {
        eventStore.update({
            filters: {
                price: value
            }
        })
    } else if (type === 'location') {
        eventStore.update({
            location: {
                city: value.city,
                lat: value.lat,
                lng: value.lng,
                live: value.live !== undefined ? value.live : false
            }
        })
    } else if (type === 'category') {
        eventStore.update({
            filters: {
                categories: value
            }
        })
    } else if (type === 'tag') {
        eventStore.update({
            filters: {
                tags: value
            }
        })
    }
}

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
    // Instead of calling childNav reference, use EventStore
    eventStore.fetchEvents()
}

const updateEvents = (value) => events.value = value
const fullMap = () => mapKey.value += 1

const handlePageChange = (page) => {
    // Update page in EventStore
    eventStore.changePage(page)
    window.scrollTo(0, 0)
}

const clear = () => {
    searchData.value = { location: { ...DEFAULT_LOCATION } }
}

const handleCategoryFilter = async (categoryId) => {
    try {
        const params = new URLSearchParams(window.location.search)
        params.set('category', categoryId)
        const response = await fetch(`/api/events?${params.toString()}`)
        events.value = await response.json()
    } catch (error) {
        console.error('Error filtering by category:', error)
    }
}

// Touch handling methods
const handleTouchStart = (e) => {
    touchStart.value = e.touches[0].clientY
    initialPosition.value = listPosition.value
}

const handleTouchMove = (e) => {
    if (!touchStart.value) return
    
    const currentTouch = e.touches[0].clientY
    const diff = touchStart.value - currentTouch
    
    // Calculate new position
    const newPosition = initialPosition.value + diff
    
    // Update expanded state based on scroll position
    isExpanded.value = newPosition <= 0
    
    // Update position with boundaries
    listPosition.value = Math.max(Math.min(newPosition, window.innerHeight - 50), 0)
}

const handleTouchEnd = () => {
    touchStart.value = null
    initialPosition.value = null
    
    const threshold = window.innerHeight * 0.3
    if (listPosition.value > threshold) {
        listPosition.value = window.innerHeight - 50
    } else {
        listPosition.value = 0
        isExpanded.value = true
    }
}

const handleMapClick = () => {
    isMapFocused.value = true
    listPosition.value = window.innerHeight - 50
    userMovedMap.value = true
}

const toggleListPosition = () => {
    const maxHeight = window.innerHeight
    isMapFocused.value = !isMapFocused.value
    listPosition.value = isMapFocused.value ? maxHeight - 50 : 0
}

const showFullMap = () => {
    searchData.value.location.fullMap = true;
    document.body.classList.add('noscroll');
    // Need this for the fullscreen toggle but don't reset position
    mapKey.value += 1;
}

const hideFullMap = () => {
    searchData.value.location.fullMap = false;
    document.body.classList.remove('noscroll');
    // Need this for the fullscreen toggle but don't reset position
    mapKey.value += 1;
}

// Lifecycle
onMounted(() => {
    // Listen for filter-update events
    window.addEventListener('filter-update', handleFilterUpdate)
    
    // Subscribe to EventStore for updates
    unsubscribe.value = eventStore.subscribe(state => {
        // Always update the events list
        if (state.events.data) {
            events.value = state.events;
        }
        
        // Always update the name and live status
        searchData.value.location.name = state.location.city || searchData.value.location.name;
        searchData.value.location.live = state.location.live || false;
        
        // Only update the map center if:
        // 1. It's a completely new city search (different from last search)
        // 2. We have valid coordinates 
        if (state.location.city && 
            state.location.city !== lastSearchCity.value &&
            state.location.lat && 
            state.location.lng) {
            
            console.log('New city search detected, updating map position to:', state.location.city);
            
            // Update center for new city search
            searchData.value.location.center = [state.location.lat, state.location.lng];
            
            // Remember this city so we don't reset again until a new city
            lastSearchCity.value = state.location.city;
            
            // Reset user movement tracking for the new search
            userMovedMap.value = false;
        }
    })
    
    // Initialize lastSearchCity with current city if available
    if (searchData.value.location.name && searchData.value.location.name !== 'Search by City') {
        lastSearchCity.value = searchData.value.location.name;
    }
    
    // Broadcast max price to other components
    window.dispatchEvent(new CustomEvent('max-price-update', { 
        detail: props.maxPrice 
    }))
    
    // Initialize mobile view properties
    listPosition.value = 0 // Start with list fully visible
    
    // Calculate initial scroll height
    scrollHeight.value = window.innerHeight * 0.4
    
    // Calculate map height
    mapHeight.value = window.innerHeight * 0.4 // 40vh equivalent
    
    // Add resize listener
    window.addEventListener('resize', handleResize)
    
    // Initialize EventStore with initial data if available
    if (props.searchedEvents) {
        eventStore.state.events = {
            ...props.searchedEvents,
            loading: false
        }
    }
})

onUnmounted(() => {
    // Clean up listeners
    window.removeEventListener('filter-update', handleFilterUpdate)
    window.removeEventListener('resize', handleResize)
    
    // Clean up EventStore subscription
    if (unsubscribe.value) {
        unsubscribe.value()
    }
})

// Handle window resize
const handleResize = () => {
    scrollHeight.value = window.innerHeight * 0.7
    mapHeight.value = window.innerHeight * 0.4
}

// Watch for URL changes and update as needed
watch(() => window.location.search, () => {
    // Let EventStore handle URL changes, it will notify us through subscription
    eventStore.initializeFromUrl()
    eventStore.fetchEvents()
})
</script>
<style scoped>
.event-search {
    touch-action: pan-y;
}

/* Allow scrolling within the content area */
.overflow-y-auto {
    -webkit-overflow-scrolling: touch;
    touch-action: pan-y pinch-zoom;
}
</style>
