<template>
    <div class="max-w-screen-5xl mx-auto px-32 py-16">
        <!-- Main Content -->
        <event-grid 
            :items="events.data"
            :columns="6"
        />

        <!-- Pagination -->
        <div v-if="events.last_page > 1" class="mt-12">
            <pagination 
                :pagination="{
                    current_page: events.current_page,
                    last_page: events.last_page,
                    from: events.from,
                    to: events.to,
                    total: events.total
                }"
                @paginate="handlePageChange"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import EventGrid from '@/GlobalComponents/Grid/event-grid.vue'
import Pagination from '@/GlobalComponents/pagination.vue'

const props = defineProps({
    searchedEvents: {
        type: Object,
        required: true
    },
    categories: {
        type: Array,
        required: true
    },
    tags: {
        type: Array,
        required: true
    },
    searchedCategories: {
        type: Array,
        default: () => []
    },
    searchedTags: {
        type: Array,
        default: () => []
    },
    maxPrice: {
        type: Number,
        required: true
    }
})

const events = ref({
    data: props.searchedEvents?.data || [],
    total: props.searchedEvents?.total || 0,
    current_page: props.searchedEvents?.current_page || 1,
    last_page: props.searchedEvents?.last_page || 1,
    from: props.searchedEvents?.from || 0,
    to: props.searchedEvents?.to || 0
})

const activeFilters = ref({
    categories: [],
    tags: [],
    price: { min: 0, max: 100 },
    dates: { start: null, end: null }
})

const handlePageChange = (page) => {
    const params = new URLSearchParams(window.location.search)
    params.set('page', page)
    window.location.href = `${window.location.pathname}?${params.toString()}`
}

const handleFilterUpdate = async (event) => {
    const { type, value } = event.detail
    
    if (type === 'price') {
        activeFilters.value.price = {
            min: value[0],
            max: value[1]
        }
    } else {
        activeFilters.value[type] = value
    }
    
    try {
        const params = new URLSearchParams(window.location.search)
        
        if (activeFilters.value.category?.length) {
            params.set('category', activeFilters.value.category)
        }
        
        if (activeFilters.value.tag?.length) {
            params.set('tag', activeFilters.value.tag)
        }
        
        if (type === 'price') {
            params.set('price0', value[0])
            params.set('price1', value[1])
        }
        
        if (activeFilters.value.dates?.start || activeFilters.value.dates?.end) {
            params.set('start', activeFilters.value.dates.start)
            params.set('end', activeFilters.value.dates.end)
        }
        
        const response = await axios.get(`/api/index/search?${params.toString()}`)
        events.value = response.data
        
        console.log('Got new search results with maxPrice:', response.data.maxPrice)
        if (response.data.maxPrice) {
            console.log('Emitting updated max price:', response.data.maxPrice)
            window.dispatchEvent(new CustomEvent('max-price-update', {
                detail: response.data.maxPrice
            }))
        }
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

onMounted(async () => {
    window.addEventListener('filter-update', handleFilterUpdate)
    initializeFiltersFromUrl()
    
    await nextTick()
    
    // Wait for QuickBar to be ready
    window.addEventListener('quickbar-ready', () => {
        console.log('Search component received quickbar-ready')
        if (props.maxPrice) {
            console.log('Emitting initial max price:', props.maxPrice)
            window.dispatchEvent(new CustomEvent('max-price-update', {
                detail: props.maxPrice
            }))
        }
    }, { once: true }) // Only listen once
})

onUnmounted(() => {
    window.removeEventListener('filter-update', handleFilterUpdate)
})

const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL)
</script>