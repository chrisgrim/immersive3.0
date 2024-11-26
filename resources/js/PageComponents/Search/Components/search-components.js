import { ref, onMounted, onUnmounted, watch } from 'vue'
import axios from 'axios'

export default function useEventSearch(props) {
    // Shared refs
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

    // Shared filter handling
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
            
            // Preserve existing search parameters
            const currentParams = new URLSearchParams(window.location.search)
            const preserveParams = ['searchType', 'lat', 'lng', 'live', 'city']
            preserveParams.forEach(param => {
                if (currentParams.has(param)) {
                    params.set(param, currentParams.get(param))
                }
            })
            
            // Add filter parameters
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
            
            // Update URL without reload
            window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`)
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

    // Move handleCategoryFilter into composable
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

    // Add the watch inside the composable
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

    // Shared lifecycle hooks
    onMounted(() => {
        console.log('Component mounted, maxPrice:', props.maxPrice)
        window.addEventListener('filter-update', handleFilterUpdate)
        initializeFiltersFromUrl()
        window.dispatchEvent(new CustomEvent('max-price-update', {
            detail: props.maxPrice
        }))
    })

    onUnmounted(() => {
        window.removeEventListener('filter-update', handleFilterUpdate)
    })

    return {
        events,
        activeFilters,
        handleFilterUpdate,
        initializeFiltersFromUrl,
        handleCategoryFilter
    }
}