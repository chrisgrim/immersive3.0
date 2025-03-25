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
                :source="searchData.location.live ? 'eventStore' : 'initialSearch'"
                @fullMap="fullMap"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import EventList from '@/GlobalComponents/Grid/event-grid.vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import Map from './Components/map.vue'
import SearchStore from '@/Stores/SearchStore.vue'

// Constants
const DEFAULT_LOCATION = {
    name: 'Search by City',
    fullMap: false,
    live: false,
    zoom: 13,
    center: [40.7127753, -74.0059728],
    mapboundary: null
}

// Props
const props = defineProps({
    searchedEvents: Object,
    user: Object
})

// Refs & State
const mapKey = ref(0)
const events = ref({
    data: props.searchedEvents?.data || [],
    total: props.searchedEvents?.total || 0,
    current_page: props.searchedEvents?.current_page || 1,
    per_page: props.searchedEvents?.per_page || 20,
    from: props.searchedEvents?.from || null,
    to: props.searchedEvents?.to || null,
    last_page: props.searchedEvents?.last_page || 1
})
const searchData = ref({ location: { ...DEFAULT_LOCATION } })
const unsubscribe = ref(null)
const debounceBoundaryUpdateTimer = ref(null)

// Computed
const hasEvents = computed(() => events.value.data && events.value.data.length)


const handlePageChange = async (page) => {
    const params = new URLSearchParams(window.location.search);
    params.set('page', page);
    
    window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`);
    window.scrollTo(0, 0);

    try {
        SearchStore.setLoading(true);
        const response = await axios.get(`/api/index/search?${params.toString()}`);
        SearchStore.updateState(response.data);
    } catch (error) {
        console.error('Error changing page:', error);
    } finally {
        SearchStore.setLoading(false);
    }
}

const fullMap = () => {
    searchData.value.location.fullMap = !searchData.value.location.fullMap;
}

// Lifecycle
onMounted(() => {

    // Subscribe to SearchStore updates
    unsubscribe.value = SearchStore.subscribe(state => {
        // Update events from store
        events.value = state.events;
        
        // Update location data if it exists in store
        if (state.location?.city) {
            searchData.value.location = {
                ...searchData.value.location,
                name: state.location.city,
                center: [
                    state.location.lat || 40.7127753,
                    state.location.lng || -74.0059728
                ],
                live: state.location.live || false,
                zoom: 13
            };
        }
    });
})

onUnmounted(() => {
    if (unsubscribe.value) {
        unsubscribe.value();
    }
    
    if (debounceBoundaryUpdateTimer.value) {
        clearTimeout(debounceBoundaryUpdateTimer.value);
    }
})
</script>