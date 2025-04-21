<template>
    <div class="max-w-screen-5xl mx-auto px-10 md:px-32 py-16">
        <!-- Main Content -->
        <event-grid 
            :items="events.data"
            :columns="6"
            :show-location="true"
        />

        <!-- Pagination -->
        <div v-if="events.last_page > 1" class="mt-12">
            <Pagination 
                v-if="events"
                class="mt-6"
                :pagination="events"
                @paginate="handlePageChange"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import EventGrid from '@/GlobalComponents/Grid/event-grid.vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import SearchStore from '@/Stores/SearchStore.vue'

// Props
const props = defineProps({
    searchedEvents: {
        type: Object,
        required: true
    }
})

// Refs & State
const events = ref({
    data: props.searchedEvents?.data || [],
    total: props.searchedEvents?.total || 0,
    current_page: props.searchedEvents?.current_page || 1,
    last_page: props.searchedEvents?.last_page || 1,
    from: props.searchedEvents?.from || 0,
    to: props.searchedEvents?.to || 0,
    per_page: props.searchedEvents?.per_page || 20
})

const unsubscribe = ref(null)

// Computed
const hasEvents = computed(() => events.value.data && events.value.data.length > 0)
const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL)

// Methods
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
        events.value = state.events
    })

    // Initialize from URL if needed
    const params = new URLSearchParams(window.location.search)
    if (params.has('page')) {
        const page = parseInt(params.get('page'))
        if (page && page !== events.value.current_page) {
            handlePageChange(page)
        }
    }
})

onUnmounted(() => {
    if (unsubscribe.value) {
        unsubscribe.value()
    }
})
</script>