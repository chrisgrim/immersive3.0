<template>
    <div class="event-search relative min-h-[calc(100vh-7rem)]">
        <div class="w-full flex">
            <section 
                :class="{ 'w-0 hidden' : searchData.location.fullMap }"
                class="z-10 relative inline-block w-[59%] min-h-[calc(100vh-8rem)]">
                <div class="inline-block text-left pt-16 pb-4 px-8">
                    <p v-if="events.data && events.data.length">{{ events.total }} immersive events.</p>
                    <p v-else>There are no location based events in {{ searchData.location.name }} with these filters.</p>
                </div>
                
                <div class="w-full">     
                    <div class="px-8">
                        <EventList 
                            v-if="events.data.length"
                            :user="user"
                            :items="events.data" />
                        <div class="mt-6">
                            <Pagination 
                                v-if="events"
                                :pagination="events"
                                @paginate="handlePageChange"
                            />
                        </div>
                    </div>
                </div>
            </section>
            <Map
                @submit="onSubmit"
                @fullMap="fullMap"
                v-model="searchData"
                :key="mapKey"
                :events="events.data" />
        </div>
    </div>
</template>

<script setup>
import { ref, defineProps, onMounted, watch, onUnmounted } from 'vue'
import Nav from './Components/nav.vue'
import EventList from './Components/album.vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import Map from './Components/map.vue'
import axios from 'axios'

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

const handleFilterUpdate = async (event) => {
    const { type, value } = event.detail
    
    if (type === 'price') {
        activeFilters.value.price = {
            min: value[0],
            max: value[1]
        }
    } else {
        activeFilters.value[type === 'category' ? 'categories' : type] = value
    }
    
    try {
        const currentParams = new URLSearchParams(window.location.search)
        const params = new URLSearchParams()

        const locationParams = ['searchType', 'lat', 'lng', 'live', 'NElat', 'NElng', 'SWlat', 'SWlng']
        locationParams.forEach(param => {
            if (currentParams.has(param)) {
                params.set(param, currentParams.get(param))
            }
        })
        
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
        
        const response = await axios.get(`/api/index/search?${params.toString()}`)
        events.value = response.data
    } catch (error) {
        console.error('Error applying filters:', error)
    }
}

const initializeFiltersFromUrl = () => {
    const params = new URLSearchParams(window.location.search)
    
    if (params.has('category')) {
        activeFilters.value.categories = params.get('category').split(',')
    }
    
    if (params.has('tags')) {
        activeFilters.value.tags = params.get('tags').split(',')
    }
    
    if (params.has('price0') || params.has('price1')) {
        const min = params.get('price0') || 0
        const max = params.get('price1') || 670
        activeFilters.value.price = [parseInt(min), parseInt(max)]
    }
    
    if (params.has('start') || params.has('end')) {
        activeFilters.value.dates = {
            start: params.get('start'),
            end: params.get('end')
        }
    }
}

onMounted(() => {
    console.log('Location mounted, maxPrice:', props.maxPrice)
    window.addEventListener('filter-update', handleFilterUpdate)
    initializeFiltersFromUrl()
    window.dispatchEvent(new CustomEvent('max-price-update', {
        detail: props.maxPrice
    }));
})

onUnmounted(() => {
    window.removeEventListener('filter-update', handleFilterUpdate)
})

const onSubmit = () => {
    try {
        if (childNav.value) {
            childNav.value.onSearch()
        } else {
            console.warn('Nav component reference is not available')
        }
    } catch (error) {
        console.error('Error in onSubmit:', error)
    }
}

const updateEvents = (value) => {
    try {
        events.value = value
    } catch (error) {
        console.error('Error updating events:', error)
    }
}

const fullMap = () => {
    try {
        mapKey.value += 1
    } catch (error) {
        console.error('Error in fullMap:', error)
    }
}

const handlePageChange = (page) => {
    try {
        if (childNav.value) {
            childNav.value.onNext(page)
            window.scrollTo(0, 0)
        } else {
            console.warn('Nav component reference is not available')
        }
    } catch (error) {
        console.error('Error in handlePageChange:', error)
    }
}

const clear = () => {
    try {
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
    } catch (error) {
        console.error('Error in clear:', error)
    }
}

const handleCategoryFilter = async (categoryId) => {
    try {
        const params = new URLSearchParams(window.location.search)
        params.set('category', categoryId)
        
        const response = await fetch(`/api/events?${params.toString()}`)
        const data = await response.json()
        
        events.value = data
    } catch (error) {
        console.error('Error filtering by category:', error)
    }
}

watch(
    () => window.location.search,
    async (newSearch) => {
        const params = new URLSearchParams(newSearch)
        const categoryId = params.get('category')
        
        if (categoryId) {
            await handleCategoryFilter(categoryId)
        }
    }
)
</script>