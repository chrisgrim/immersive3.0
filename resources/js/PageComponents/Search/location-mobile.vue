<template>
    <div class="event-search relative">
        <div class="w-full md:flex">
            <!-- Map Component (Background) -->
            <div class="fixed top-0 left-0 right-0 bottom-[40vh] transition-all duration-300 ease-in-out"
                 :class="isFullMap ? 'z-[49]' : 'z-[48]'">
                <Map
                    v-model="isFullMap"
                    :key="mapKey"
                    :events="events.data"
                    :full-map="isFullMap"
                />
            </div>

            <div 
                :style="{ height: `${mapHeight}px` }"
                @click="showFullMap"
                class="w-full relative z-[49] transition-all duration-300"
                :class="isFullMap ? 'opacity-0 pointer-events-none' : ''" />

            <!-- Events List Section -->
            <div 
                :style="{
                    transform: isFullMap 
                        ? `translate3d(0, ${mapHeight}px, 0)` 
                        : 'translate3d(0, 0px, 0)'
                }"
                class="min-h-[64vh] relative w-full z-49 transition-transform duration-300">
                <div class="bg-white rounded-t-5xl shadow-custom-6">
                    <div 
                        @click="hideFullMap"
                        class="w-full flex items-center gap-4 pt-4 h-28 relative before:content-[''] before:block before:absolute before:top-6 before:left-1/2 before:-translate-x-5 before:w-16 before:h-2 before:rounded-full before:bg-[#351b1b7a]">
                        <p class="text-black text-1xl font-medium w-full text-center" v-if="hasEvents">{{ events.total }} immersive events.</p>
                        <p class="text-black text-1xl font-medium w-full text-center" v-else>No events found.</p>
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
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import EventList from '@/GlobalComponents/Grid/event-grid.vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import Map from './Components/map-mobile.vue'
import SearchStore from '@/Stores/SearchStore.vue'

// Props
const props = defineProps({
    searchedEvents: Object,
    user: Object
})

// Refs & State
const mapKey = ref(0)
const isFullMap = ref(false)
const events = ref({
    data: props.searchedEvents?.data || [],
    total: props.searchedEvents?.total || 0,
    current_page: props.searchedEvents?.current_page || 1,
    per_page: props.searchedEvents?.per_page || 20,
    from: props.searchedEvents?.from || null,
    to: props.searchedEvents?.to || null,
    last_page: props.searchedEvents?.last_page || 1
})
const unsubscribe = ref(null)

// Mobile-specific refs that are needed for the template
const mapHeight = ref(0)
const listPosition = ref(0)
const isMapFocused = ref(false)
const scrollHeight = ref(0)

// Computed
const hasEvents = computed(() => events.value.data && events.value.data.length)

// Event handlers
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

const showFullMap = () => {
    window.scrollTo(0, 0);
    isFullMap.value = true;
    document.body.classList.add('noscroll');
    mapKey.value += 1;
}

const hideFullMap = () => {
    isFullMap.value = false;
    document.body.classList.remove('noscroll');
    mapKey.value += 1;
}

// Lifecycle
onMounted(() => {
    // Subscribe to SearchStore updates
    unsubscribe.value = SearchStore.subscribe(state => {
        events.value = state.events;
    });

    // Mobile-specific initialization
    mapHeight.value = window.innerHeight * 0.4;
    scrollHeight.value = window.innerHeight * 0.7;
    listPosition.value = 0;
    
    window.addEventListener('resize', handleResize);
})

onUnmounted(() => {
    // Clean up listeners
    window.removeEventListener('resize', handleResize);
    
    if (unsubscribe.value) {
        unsubscribe.value();
    }
})

// Handle window resize
const handleResize = () => {
    scrollHeight.value = window.innerHeight * 0.7;
    mapHeight.value = window.innerHeight * 0.4;
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
