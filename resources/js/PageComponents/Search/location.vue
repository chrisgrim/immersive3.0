<template>
    <div class="event-search relative min-h-[calc(100vh-7rem)]">
        <div class="w-full flex">
            <!-- Events List Section -->
            <section 
                :class="{ 'w-0 hidden' : searchData.location.fullMap }"
                class="z-10 relative inline-block w-[59%] min-h-[calc(100vh-8rem)]">
                <div class="inline-block text-left pt-16 pb-4 px-8">
                    <p v-if="events.data && events.data.length">{{ events.total }} immersive events.</p>
                    <p v-else>There are no location based events in {{ searchData.location.name }} with these filters.</p>
                </div>
                
                <div class="px-8">
                    <EventList
                        v-if="events.data.length"
                        :items="events.data"
                        :user="user"
                        :columns="4"
                    />
                    <div class="mt-6">
                        <Pagination 
                            v-if="events"
                            :pagination="events"
                            @paginate="handlePageChange"
                        />
                    </div>
                </div>
            </section>

            <!-- Map Component -->
            <Map
                @submit="onSubmit"
                @fullMap="fullMap"
                v-model="searchData"
                :key="mapKey"
                :events="events.data" 
            />
        </div>
    </div>
</template>

<script setup>
import { ref, defineProps, onMounted, watch, onUnmounted } from 'vue'
import Nav from './Components/nav.vue'
import EventList from '@/GlobalComponents/Grid/event-grid.vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import Map from './Components/map.vue'
import axios from 'axios'

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
const events = ref({
    data: props.searchedEvents?.data || [],
    total: props.searchedEvents?.total || 0
})
const mapKey = ref(0)
const searchData = ref({
    location: {
        name: 'Search by City',
        fullMap: false,
        live: false,
        zoom: 13,
        center: [40.7127753, -74.0059728],
        mapboundary: null
    }
})
const activeFilters = ref({
    categories: [],
    tags: [],
    price: { min: 0, max: 100 },
    dates: { start: null, end: null }
})

// Filter Handling Methods
const handleFilterUpdate = async (event) => {
    const { type, value } = event.detail
    
    if (type === 'price') {
        activeFilters.value.price = { min: value[0], max: value[1] }
    } else if (type === 'location') {
        searchData.value.location = {
            ...searchData.value.location,
            name: value.city,
            center: [value.lat, value.lng],
            live: false,
            zoom: 13
        }
    } else {
        activeFilters.value[type === 'category' ? 'categories' : type] = value
    }
    
    try {
        const params = buildSearchParams(type, value)
        const response = await axios.get(`/api/index/search?${params.toString()}`)
        events.value = response.data
    } catch (error) {
        console.error('Error applying filters:', error)
    }
}

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
    if (activeFilters.value.tags.length) {
        params.set('tags', activeFilters.value.tags.join(','))
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
    if (childNav.value) {
        childNav.value.onSearch()
    } else {
        console.warn('Nav component reference is not available')
    }
}

const updateEvents = (value) => events.value = value

const fullMap = () => mapKey.value += 1

const handlePageChange = (page) => {
    if (childNav.value) {
        childNav.value.onNext(page)
        window.scrollTo(0, 0)
    } else {
        console.warn('Nav component reference is not available')
    }
}

const clear = () => {
    searchData.value = {
        location: {
            name: 'Search by City',
            fullMap: false,
            live: false,
            zoom: 13,
            center: [40.7127753, -74.0059728],
            mapboundary: null
        }
    }
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

// Lifecycle & Watchers
onMounted(() => {
    window.addEventListener('filter-update', handleFilterUpdate)
    initializeFiltersFromUrl()
    initializeLocationFromUrl()
    window.dispatchEvent(new CustomEvent('max-price-update', { detail: props.maxPrice }))
})

onUnmounted(() => {
    window.removeEventListener('filter-update', handleFilterUpdate)
})

watch(() => window.location.search, async (newSearch) => {
    const categoryId = new URLSearchParams(newSearch).get('category')
    if (categoryId) await handleCategoryFilter(categoryId)
})
</script>