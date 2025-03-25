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
                    :source="eventStore.state.source"
                    :full-map="searchData.location.fullMap"
                    @boundsChanged="handleBoundsChanged"
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
                        ? `translate3d(0, ${mapHeight}px, 0)` 
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
import EventList from '@/GlobalComponents/Grid/event-grid.vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import Map from './Components/map-mobile.vue'
import eventStore from '@/Stores/EventStore.vue'

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
    total: props.searchedEvents?.total || 0,
    current_page: props.searchedEvents?.current_page || 1,
    per_page: props.searchedEvents?.per_page || 20,
    from: props.searchedEvents?.from,
    to: props.searchedEvents?.to,
    last_page: props.searchedEvents?.last_page || 1
})
const searchData = ref({ location: { ...DEFAULT_LOCATION } })
const activeFilters = ref({ ...DEFAULT_FILTERS })
const unsubscribe = ref(null)
const userMovedMap = ref(false)

// New refs for mobile interaction
const listContainer = ref(null)
const isMapFocused = ref(false)
const listPosition = ref(0)

// Add new ref for expanded state
const isExpanded = ref(false)
const scrollHeight = ref(0)

// Add ref for map height
const mapHeight = ref(0)

// Add this near the other refs
const lastSearchCity = ref('');
const isInitialLoad = ref(true);
const debounceBoundaryUpdateTimer = ref(null)
const pendingBoundaries = ref(null)

// Computed
const hasEvents = computed(() => events.value.data && events.value.data.length)


const handleBoundsChanged = (bounds) => {
    pendingBoundaries.value = bounds;
    
    if (debounceBoundaryUpdateTimer.value) {
        clearTimeout(debounceBoundaryUpdateTimer.value);
    }
    debounceBoundaryUpdateTimer.value = setTimeout(() => {
        updateMapBoundaries();
    }, 750);
}

const updateMapBoundaries = () => {
    if (!pendingBoundaries.value) return;
    
    const bounds = pendingBoundaries.value;
    
    eventStore.update({
        source: 'eventStore',
        location: {
            city: searchData.value.location.name,
            lat: searchData.value.location.center[0],
            lng: searchData.value.location.center[1],
            searchType: 'inPerson',
            NElat: bounds.northEast.lat,
            NElng: bounds.northEast.lng,
            SWlat: bounds.southWest.lat,
            SWlng: bounds.southWest.lng,
            live: true
        }
    }, true);
    
    pendingBoundaries.value = null;
}



// Event Handlers

const handlePageChange = (page) => {
    // Update page in EventStore
    eventStore.changePage(page)
    window.scrollTo(0, 0)
}

const handleMapClick = () => {
    isMapFocused.value = true
    listPosition.value = window.innerHeight - 50
    userMovedMap.value = true
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
    eventStore.initializeFromUrl();  // Initialize URL params first
    
    // Then set initial data if this is first fetch
    if (eventStore.isFirstFetch) {
        eventStore.setInitialData(props.maxPrice, {
            data: props.searchedEvents?.data || [],
            total: props.searchedEvents?.total || 0,
            current_page: props.searchedEvents?.current_page || 1,
            per_page: props.searchedEvents?.per_page || 20,
            from: props.searchedEvents?.from,
            to: props.searchedEvents?.to,
            last_page: props.searchedEvents?.last_page || 1,
            loading: false
        });
    }
    
    unsubscribe.value = eventStore.subscribe(state => {
        if (eventStore.isUpdating && !isInitialLoad.value) return;

        console.log('this is the subscribe state being updated', state);
        
        if (state) {
            // Update events with all pagination data
            events.value = {
                data: state.events.data,
                total: state.events.total,
                current_page: state.events.current_page,
                per_page: state.events.per_page,
                from: state.events.from,
                to: state.events.to,
                last_page: state.events.last_page
            };

            // Update location data on initial load OR when source is initialSearch (new city selected)
            if (isInitialLoad.value || state.source === 'initialSearch') {
                searchData.value.location = {
                    ...searchData.value.location,
                    name: state.location?.city || 'Search by City',
                    center: [
                        state.location?.lat || 40.7127753,
                        state.location?.lng || -74.0059728
                    ],
                    live: state.location?.live || false,
                    zoom: 13
                };
                isInitialLoad.value = false;
            }
        }
    });
    
    if (window.location.pathname === '/index/search') {
        eventStore.fetchEvents();
    }

    // Rest of the mobile-specific initialization
    window.dispatchEvent(new CustomEvent('max-price-update', { 
        detail: props.maxPrice 
    }));
    
    listPosition.value = 0;
    scrollHeight.value = window.innerHeight * 0.4;
    mapHeight.value = window.innerHeight * 0.4;
    
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    // Clean up listeners
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
