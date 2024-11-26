<template>
    <div class="max-w-screen-2xl mx-auto px-32 py-16">
        <!-- Main Content -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-8">
            <div v-for="event in events.data" :key="event.id" class="flex flex-col group">
                <a :href="`/events/${event.slug}`" class="block h-full flex flex-col">
                    <!-- Event Image Container with 4:5 aspect ratio -->
                    <div class="relative mb-3 overflow-hidden rounded-lg bg-gray-100 transition-transform duration-200 ease-in-out group-hover:scale-[1.02]">
                        <!-- 4:5 aspect ratio padding trick -->
                        <div class="pb-[125%]"></div>
                        <!-- Image positioned absolutely within container -->
                        <picture v-if="event.largeImagePath" class="absolute inset-0">
                            <source 
                                type="image/webp" 
                                :srcset="`${imageUrl}${event.largeImagePath}`"
                            > 
                            <img 
                                loading="lazy" 
                                class="h-full w-full object-cover"
                                :src="`${imageUrl}${event.largeImagePath.slice(0, -4)}jpg`" 
                                :alt="`${event.name} Immersive Event`"
                            >
                        </picture>
                    </div>
                

                    <!-- Content wrapper with flex -->
                    <div class="flex flex-col flex-grow">
                        <!-- Event Title - limited to 2 lines -->
                        <h3 class="my-3 text-2xl font-semibold leading-tight line-clamp-2">{{ event.name }}</h3>
                        <p class="mb-3 text-1xl leading-normal text-gray-600 line-clamp-2">{{ event.tag_line }}</p>
                        <!-- Price pushed to bottom with margin-top auto -->
                        <p class="text-1xl font-semibold mt-auto">{{ event.price_range }}</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="events.last_page > 1" class="mt-12 flex justify-center">
            <nav class="flex space-x-2">
                <a 
                    v-for="page in events.last_page" 
                    :key="page"
                    :href="updateQueryString('page', page)"
                    :class="[
                        'px-4 py-2 rounded-lg transition-colors duration-200',
                        page === events.current_page 
                            ? 'bg-[#E94362] text-white' 
                            : 'bg-white text-gray-500 hover:bg-gray-100'
                    ]"
                >
                    {{ page }}
                </a>
            </nav>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'

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

// Add new refs
const events = ref({
    data: props.searchedEvents?.data || [],
    total: props.searchedEvents?.total || 0
})

const activeFilters = ref({
    categories: [],
    tags: [],
    price: { min: 0, max: 100 },
    dates: { start: null, end: null }
})

// Add filter handling functions
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
        const params = new URLSearchParams()
        
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

// Add lifecycle hooks
onMounted(async () => {
    console.log('All mounted, component props:', props);
    console.log('All mounted, maxPrice specifically:', props.maxPrice);
    window.addEventListener('filter-update', handleFilterUpdate);
    initializeFiltersFromUrl();
    
    // Wait for next tick to ensure QuickBar is mounted
    await nextTick();
    
    console.log('Dispatching max-price-update event');
    window.dispatchEvent(new CustomEvent('max-price-update', {
        detail: props.maxPrice
    }));
})

onUnmounted(() => {
    window.removeEventListener('filter-update', handleFilterUpdate)
})

const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL)

const updateQueryString = (key, value) => {
    const params = new URLSearchParams(window.location.search)
    params.set(key, value)
    return `${window.location.pathname}?${params.toString()}`
}
</script>