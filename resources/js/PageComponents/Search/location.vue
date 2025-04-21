<template>
    <div class="event-search relative min-h-[calc(100vh-7rem)]">
        <div class="w-full flex">
            <!-- Events List Section -->
            <section 
                :class="{ 'w-0 hidden' : isFullMap }"
                class="z-10 relative inline-block w-[59%] min-h-[calc(100vh-8rem)]"
            >
                <div class="inline-block text-left pt-16 pb-4 px-8">
                    <p v-if="hasEvents">{{ events.total }} immersive events.</p>
                    <p v-else>There are no location based events with these filters.</p>
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
                v-model="isFullMap"
                :key="mapKey"
                :events="events.data"
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
    fullMap: false
}

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

// Computed
const hasEvents = computed(() => events.value.data && events.value.data.length)

const handlePageChange = async (page) => {
    const params = new URLSearchParams(window.location.search)
    params.set('page', page)
    
    window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`)
    window.scrollTo(0, 0)

    try {
        SearchStore.setLoading(true)
        
        // Make API call
        const response = await axios.get(`/api/index/search?${params.toString()}`)
        
        // First, directly update our local component state for immediate feedback
        if (response.data && response.data.data) {
            events.value = {
                data: response.data.data || [],
                total: response.data.total || 0,
                current_page: response.data.current_page || 1,
                last_page: response.data.last_page || 1,
                from: response.data.from || 0,
                to: response.data.to || 0,
                per_page: response.data.per_page || 20
            }
            
            // Force map to re-render with new events
            mapKey.value++
        }

        // Create a properly structured data object for SearchStore
        // This ensures that SearchStore receives the complete expected structure
        const completeState = {
            events: {
                data: response.data.data || [],
                total: response.data.total || 0,
                current_page: response.data.current_page || 1,
                last_page: response.data.last_page || 1,
                from: response.data.from || 0,
                to: response.data.to || 0,
                per_page: response.data.per_page || 20
            }
        }
        
        // Then update the store (which will update any other components)
        SearchStore.updateState(completeState)
    } catch (error) {
        console.error('Error changing page:', error)
    } finally {
        SearchStore.setLoading(false)
    }
}

// Lifecycle
onMounted(() => {
    // Subscribe to SearchStore updates
    unsubscribe.value = SearchStore.subscribe(state => {
        events.value = state.events;
    });
})

onUnmounted(() => {
    if (unsubscribe.value) {
        unsubscribe.value();
    }
})
</script>