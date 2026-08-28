<template>
    <div class="max-w-screen-5xl mx-auto px-10 md:px-32 py-16">
        <!-- Active Filters -->
        <results-header :total="events.total" :initial-remote-location="searchedRemoteLocation" />

        <!-- Main Content -->
        <event-grid
            v-if="hasEvents"
            :items="events.data"
            :columns="6"
            :show-location="true"
        />

        <!-- Empty state — this page has no map to fall back to (unlike
             location.vue's SimilarResults), so "here's what's out there
             instead" is just the latest remote/At-Home events, unfiltered
             by whatever this search's own filters were. -->
        <div v-else class="py-8">
            <h2 class="text-2.5xl text-black font-medium">No events match your filters</h2>
            <p class="text-xl text-gray-700 mt-2 leading-tight">
                Try changing or removing some of your filters — here are some other events you might like:
            </p>

            <event-grid
                v-if="fallbackEvents.length"
                :items="fallbackEvents"
                :columns="6"
                :show-location="true"
                class="mt-8"
            />
        </div>

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
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import axios from 'axios'
import EventGrid from '@/GlobalComponents/Grid/event-grid.vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import SearchStore from '@/Stores/SearchStore.vue'
import ResultsHeader from './Components/results-header.vue'

// Props
const props = defineProps({
    searchedEvents: {
        type: Object,
        required: true
    },
    // Server-resolved { id, name, slug } for the URL's remoteLocation slug
    // (see ListingsController::buildSearchFilters) — passed straight through
    // to results-header.vue so it can skip its own resolution fetch. See
    // that component for the full reasoning.
    searchedRemoteLocation: {
        type: Object,
        default: null
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
let pageRequestController = null
const fallbackEvents = ref([])

// Computed
const hasEvents = computed(() => events.value.data && events.value.data.length > 0)
const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL)

// Empty-state fallback — "no events match your filters, here's what's out
// there instead." watch (not a one-off onMounted check) so this also covers
// a filter change or page change that lands on zero results after the
// initial load, not just a cold visit that started empty. Fetched once per
// empty spell (guarded so switching between two different empty results —
// e.g. paginating — doesn't refetch the exact same fallback list).
let fallbackFetched = false
watch(hasEvents, (has) => {
    if (has) {
        fallbackFetched = false
        return
    }
    if (fallbackFetched) return
    fallbackFetched = true

    axios.get('/api/events/latest-remote')
        .then((response) => {
            fallbackEvents.value = response.data?.events || []
        })
        .catch((error) => {
            console.error('[search] failed to load fallback events', error)
        })
}, { immediate: true })

// Methods
const handlePageChange = async (page) => {
    const params = new URLSearchParams(window.location.search)
    params.set('page', page)
    
    window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`)
    window.scrollTo(0, 0)

    // Cancel any in-flight pagination request so a slow earlier page can't
    // overwrite the results of a faster later one.
    if (pageRequestController) {
        pageRequestController.abort()
    }
    pageRequestController = new AbortController()

    try {
        SearchStore.setLoading(true)

        // Make API call
        const response = await axios.get(`/api/index/search?${params.toString()}`, {
            signal: pageRequestController.signal,
        })
        
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
        if (axios.isCancel?.(error) || error.name === 'CanceledError') return
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