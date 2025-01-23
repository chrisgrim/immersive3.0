<template>
    <div class="bg-white py-4 h-full">
        <div class="quick-bar-container w-full h-full mx-auto flex items-center justify-between">
            <!-- Scrollable Categories Container -->
            <div class="flex-1 mr-4 overflow-hidden transition-all duration-300 ease-in-out">
                <div class="flex w-full">
                    <!-- Visible Categories -->
                    <div class="flex w-full">
                        <button 
                            v-for="category in visibleQuickBarCategories" 
                            :key="category.id"
                            class="flex flex-col items-center w-[100px] flex-shrink-0 p-2 rounded-lg"
                            @click="selectCategory(category.id)"
                            :class="{'': isCategorySelected(category.id)}"
                        >
                            <img 
                                :src="getCategoryIcon(category)" 
                                :alt="category.name"
                                class="w-16 h-16 object-cover transition-opacity duration-200 group-hover:opacity-100"
                                :class="{
                                    'opacity-50 hover:opacity-100': !isCategorySelected(category.id), 
                                    'opacity-100': isCategorySelected(category.id)
                                }"
                            >
                            <span 
                                class="text-sm mt-1 w-full text-center break-words hyphens-auto transition-opacity duration-200 group-hover:opacity-100"
                                :class="{
                                    'opacity-50 hover:opacity-100': !isCategorySelected(category.id), 
                                    'opacity-100': isCategorySelected(category.id)
                                }"
                            >
                                {{ category.name }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex space-x-4 flex-shrink-0">
                <!-- Categories Button -->
                <button 
                    v-if="overflowCategories.length > 0"
                    @click="openModal('categories')"
                    class="p-4 rounded-2xl bg-white border border-gray-300 hover:bg-gray-200"
                    :class="{ 'bg-gray-100': selectedCategoriesCount > 0 }"
                >
                    <span class="text-lg">
                        <template v-if="selectedCategoriesCount > 0">
                            {{ selectedCategoriesCount }} Selected
                        </template>
                        <template v-else>
                            Categories
                        </template>
                    </span>
                </button>
                
                <!-- Rest of the action buttons remain unchanged -->
                <button 
                    @click="openModal('tags')"
                    class="p-4 rounded-2xl bg-white border border-gray-300 hover:bg-gray-200"
                    :class="{ 'bg-gray-100': selectedTags.length > 0 }"
                >
                    <span class="text-lg">
                        <template v-if="selectedTags.length > 0">
                            {{ selectedTags.length }} Selected
                        </template>
                        <template v-else>
                            Tags
                        </template>
                    </span>
                </button>
                
                <button 
                    v-if="shouldShowPriceFilter"
                    @click="openModal('price')"
                    class="p-4 rounded-2xl bg-white border border-gray-300 hover:bg-gray-200"
                    :class="{ 'bg-gray-100': priceRange[0] !== 0 || priceRange[1] !== maxPrice }"
                >
                    <span class="text-lg">
                        <template v-if="priceRange[0] !== 0 || priceRange[1] !== maxPrice">
                            ${{ priceRange[0] }} - ${{ priceRange[1] }}
                        </template>
                        <template v-else>
                            Price
                        </template>
                    </span>
                </button>
            </div>
        </div>

        <!-- Modals -->
        <CategoriesModal 
            v-if="activeModal === 'categories'" 
            :categories="modalCategories"
            :selected-categories="selectedCategories"
            @update:selected="selectCategory"
            @close="closeModal"
        />
        <TagsModal 
            v-if="activeModal === 'tags'" 
            :genres="genres"
            :selected-tags="selectedTags"
            @update:selected="selectTags"
            @close="closeModal"
        />
        <PriceModal
        v-if="activeModal === 'price'"
        :price-range="priceRange"
        :max-price="maxPrice"
        @update:price="handlePriceUpdate"
        @close="closeModal"
    />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import CategoriesModal from './Components/categories.vue'
import TagsModal from './Components/tags.vue'
import PriceModal from './Components/price.vue'

const imageUrl = import.meta.env.VITE_IMAGE_URL
const emit = defineEmits(['categorySelected'])
const selectedCategories = ref([])
const selectedTags = ref([])
const dateRange = ref({ start: null, end: null })

// Props definition
const props = defineProps({
    showPriceFilter: {
        type: Boolean,
        default: false
    }
})

const maxPrice = ref(0)
const priceRange = ref([0, 0])

// Add a watcher for the URL searchType
const searchType = computed(() => {
    const params = new URLSearchParams(window.location.search)
    return params.get('searchType')
})

// Watch for searchType changes to determine if we show price filter
watch(searchType, (newType) => {
    if (newType === 'allEvents' || newType === 'inPerson') {
        // Check URL for maxPrice
        const params = new URLSearchParams(window.location.search)
        if (params.has('maxPrice')) {
            const newMaxPrice = parseInt(params.get('maxPrice'))
            maxPrice.value = newMaxPrice
            if (!params.has('price0') && !params.has('price1')) {
                priceRange.value = [0, newMaxPrice]
            }
        }
    }
})

// Add this computed property
const shouldShowPriceFilter = computed(() => {
    return searchType.value === 'allEvents' || searchType.value === 'inPerson'
})

// First define the handler
const handleMaxPriceUpdate = (event) => {
    console.log('QuickBar received max-price-update event:', event.detail)
    maxPrice.value = event.detail
    console.log('QuickBar updated maxPrice to:', maxPrice.value)
    
    // Set initial price range if it hasn't been set yet
    if (priceRange.value[1] === 0) {
        priceRange.value = [0, event.detail]
        console.log('QuickBar initialized priceRange to:', priceRange.value)
    }
}

onMounted(() => {
    console.log('QuickBar mounted')
    
    // Initialize searchType check
    if (searchType.value === 'allEvents' || searchType.value === 'inPerson') {
        const params = new URLSearchParams(window.location.search)
        console.log('Checking URL params for price:', params.toString())
        
        if (params.has('price0') && params.has('price1')) {
            priceRange.value = [
                parseInt(params.get('price0')),
                parseInt(params.get('price1'))
            ]
            console.log('Set price range from URL:', priceRange.value)
        }
    }
})

const categories = ref([])

// Update the categories fetch in onMounted
onMounted(async () => {
    try {
        console.log('QuickBar mounted')
        
        // Add max price listener and signal ready
        window.addEventListener('max-price-update', handleMaxPriceUpdate)
        window.dispatchEvent(new CustomEvent('quickbar-ready'))
        
        // Keep these API calls
        const response = await axios.get('/api/categories/active/cached');
        categories.value = response.data;
        
        const genresResponse = await axios.get('/api/genres/active/cached');
        genres.value = genresResponse.data;
        
        // Initialize price range from maxPrice prop/event instead
        if (!priceRange.value[1]) {
            priceRange.value = [0, maxPrice.value];
        }
        
        // Rest of the initialization code...
        const params = new URLSearchParams(window.location.search);
        
        if (params.has('category')) {
            const categoryIds = params.get('category').split(',');
            selectedCategories.value = categoryIds.map(id => parseInt(id));
        }
        
        if (params.has('tag')) {
            const tagIds = params.get('tag').split(',');
            selectedTags.value = tagIds.map(id => parseInt(id));
        }
        
        window.addEventListener('category-update', handleCategoryUpdate);
    } catch (error) {
        console.error('Error fetching data:', error);
    }
});

// First 10 categories for the quick bar
const quickBarCategories = computed(() => {
    return visibleCategories.value.slice(0, 10)
})

// All visible categories (filtered by location if needed)
const visibleCategories = computed(() => {
    const isLocationSearch = window.location.pathname === '/index/search' && 
                           new URLSearchParams(window.location.search).get('searchType') === 'inPerson'
    
    if (isLocationSearch) {
        const filtered = categories.value.filter(category => !category.remote);
        return filtered;
    }
    
    return categories.value;
})

// Categories for the modal (excluding the ones in quick bar)
const modalCategories = computed(() => {
    const isLocationSearch = window.location.pathname === '/index/search' && 
                           new URLSearchParams(window.location.search).get('searchType') === 'inPerson'
    
    // Filter categories based on location search
    const filteredCategories = isLocationSearch 
        ? categories.value.filter(category => !category.remote)
        : categories.value

    // Return categories that aren't in the visible quick bar
    return filteredCategories.slice(visibleCategoryCount.value)
})

const getCategoryIcon = (category) => {
    return category.images?.find(img => img.rank === 1)?.thumb_image_path 
        ? `${imageUrl}${category.images.find(img => img.rank === 1).thumb_image_path}`
        : ''
}

const emitFilterUpdate = (filterType, value) => {
    console.log(`Emitting filter update for ${filterType}:`, value);
    
    // Create the parameter object based on filter type
    let params = {};
    if (filterType === 'category') {
        params.category = value;  // Keep array format for category
    } else if (filterType === 'tag') {
        params.tag = value;  // Keep array format for tag too
    } else if (filterType === 'price') {
        params.price0 = value[0];
        params.price1 = value[1];
    }
    
    window.dispatchEvent(new CustomEvent('filter-update', {
        detail: {
            type: filterType,
            value: value,
            params: params
        }
    }));
}

const selectCategory = (categoryIds) => {
    // If it's a single ID (from quickbar), convert to array
    const newCategories = Array.isArray(categoryIds) ? categoryIds : [categoryIds];
    
    // If we're on the homepage, handle redirect
    if (window.location.pathname === '/' || window.location.pathname === '/index') {
        window.location.href = `/index/search?category=${newCategories[0]}&searchType=allEvents`;
        return;
    }
    
    // For modal selections, completely replace the selection
    if (Array.isArray(categoryIds)) {
        selectedCategories.value = [...new Set(categoryIds)]; // Remove duplicates
    } else {
        // For quickbar toggles, toggle single category
        const index = selectedCategories.value.indexOf(categoryIds);
        if (index === -1) {
            selectedCategories.value = [...new Set([...selectedCategories.value, categoryIds])];
        } else {
            selectedCategories.value = selectedCategories.value.filter(id => id !== categoryIds);
        }
    }
    
    // Update URL and emit filter update
    const params = new URLSearchParams(window.location.search);
    if (selectedCategories.value.length > 0) {
        params.set('category', [...new Set(selectedCategories.value)].join(','));
    } else {
        params.delete('category');
    }
    window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`);
    
    emitFilterUpdate('category', selectedCategories.value);
}

const selectTags = (tagIds) => {
    // If it's a single ID (from quickbar), convert to array
    const newTags = Array.isArray(tagIds) ? tagIds : [tagIds];
    
    // If we're on the homepage, handle redirect
    if (window.location.pathname === '/' || window.location.pathname === '/index') {
        window.location.href = `/index/search?tag=${newTags[0]}&searchType=allEvents`;
        return;
    }
    
    selectedTags.value = newTags;
    
    // Update URL parameters
    const params = new URLSearchParams(window.location.search);
    
    // Update exactly like categories
    if (selectedTags.value.length > 0) {
        params.set('tag', selectedTags.value.toString());  // Convert array to string
    } else {
        params.delete('tag');
    }
    
    // Preserve searchType
    if (!params.has('searchType')) {
        params.set('searchType', 'allEvents');
    }
    
    // Update URL
    const newUrl = `${window.location.pathname}?${params.toString()}`;
    window.history.pushState({}, '', newUrl);
    
    // Emit filter update with the same format as categories
    emitFilterUpdate('tag', selectedTags.value.toString());
}

const handlePriceUpdate = (newPrice) => {
    console.log('Price update received:', newPrice)
    priceRange.value = newPrice
    
    // Dispatch the event in the format the search component expects
    window.dispatchEvent(new CustomEvent('filter-update', {
        detail: {
            type: 'price',
            value: newPrice
        }
    }))
    
    // Update URL
    const params = new URLSearchParams(window.location.search)
    params.set('price0', newPrice[0].toString())
    params.set('price1', newPrice[1].toString())
    window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`)
    
    // Close the modal
    closeModal()
}

const updateDateRange = (range) => {
    dateRange.value = range
    
    const params = new URLSearchParams(window.location.search)
    params.set('start', range.start)
    params.set('end', range.end)
    window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`)
    
    emitFilterUpdate('dates', range)
}

// Single modal state
const activeModal = ref(null)

const openModal = (modalName) => {
    activeModal.value = modalName
}

const closeModal = () => {
    activeModal.value = null
}

// Update button classes to show selected state
const isCategorySelected = (categoryId) => {
    return selectedCategories.value.includes(categoryId)
}

const isPriceModalOpen = ref(false)

// Update the categories button to use filtered categories
const selectedCategoriesCount = computed(() => {
    return selectedCategories.value.filter(id => 
        visibleCategories.value.some(cat => cat.id === id)
    ).length
})

// Add this with your other event handlers
const handleCategoryUpdate = (event) => {
    const categoryId = event.detail
    
    // Clear existing selections and set the new one
    selectedCategories.value = [categoryId]
    
    // Update URL (this part is already handled by album.vue, but included for completeness)
    const params = new URLSearchParams(window.location.search)
    params.set('category', categoryId.toString())
    window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`)
}

// Clean up in onUnmounted
onUnmounted(() => {
    window.removeEventListener('max-price-update', handleMaxPriceUpdate)
    // Add this line to clean up the category listener
    window.removeEventListener('category-update', handleCategoryUpdate)
})

// Add this with your other refs
const genres = ref([])

// Add this to your onMounted function
onMounted(async () => {
    try {
        // Existing categories fetch...

        // Add genres fetch
        const genresResponse = await axios.get('/api/genres/active/cached');
        genres.value = genresResponse.data;
        
        // Initialize selected tags from URL
        const params = new URLSearchParams(window.location.search);
        if (params.has('tags')) {
            const tagIds = params.get('tags').split(',');
            selectedTags.value = tagIds.map(id => parseInt(id));
        }
    } catch (error) {
        console.error('Error fetching data:', error);
    }
});

// Add these new computed properties
const containerWidth = ref(0)
const categoryWidth = 100 // Width of each category in pixels
const actionButtonsWidth = 180 // Width reserved for action buttons

// Update the calculation to be more precise and consider full width
const visibleCategoryCount = computed(() => {
    if (containerWidth.value === 0) return 0
    const availableWidth = containerWidth.value - actionButtonsWidth - 16 // Subtract margin
    const count = Math.floor(availableWidth / categoryWidth)
    return Math.max(0, Math.min(count, visibleCategories.value.length))
})

// Split categories into visible and overflow
const visibleQuickBarCategories = computed(() => {
    return visibleCategories.value.slice(0, visibleCategoryCount.value)
})

const overflowCategories = computed(() => {
    return visibleCategories.value.slice(visibleCategoryCount.value)
})

// Initialize the container width measurement
onMounted(() => {
    // Initial measurement
    const updateContainerWidth = () => {
        const container = document.querySelector('.quick-bar-container')
        if (container) {
            containerWidth.value = container.offsetWidth
        }
    }

    // Set up ResizeObserver
    const observer = new ResizeObserver(() => {
        updateContainerWidth()
    })

    // Initial measurement and start observing
    const container = document.querySelector('.quick-bar-container')
    if (container) {
        updateContainerWidth()
        observer.observe(container)
    }

    // Cleanup
    onUnmounted(() => {
        if (container) {
            observer.unobserve(container)
        }
        observer.disconnect()
    })
})
</script>
