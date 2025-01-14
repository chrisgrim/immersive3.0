<template>
    <div class="bg-white py-4 h-full">
        <div class="w-full h-full mx-auto flex items-center justify-between">
            <!-- Scrollable Categories (first 10 only) -->
            <div class="flex-1 flex space-x-4 overflow-x-auto">
                <button 
                    v-for="category in quickBarCategories" 
                    :key="category.id"
                    class="flex flex-col items-center min-w-[64px] p-2"
                    @click="selectCategory(category.id)"
                    :class="{'bg-gray-100 rounded-lg': isCategorySelected(category.id)}"
                >
                    <img 
                        :src="getCategoryIcon(category)" 
                        :alt="category.name"
                        class="w-16 h-16 object-cover"
                    >
                    <span class="text-sm mt-1 max-w-[10rem] text-center break-words">{{ category.name }}</span>
                </button>
            </div>

            <!-- Action Buttons -->
            <div class="flex space-x-4">
                <!-- Categories Button -->
                <button 
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
                
                <!-- Tags Button -->
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
                
                <!-- Price Button -->
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
                
                <PriceModal 
                    v-if="activeModal === 'price'"
                    :model-value="priceRange"
                    :max-price="maxPrice"
                    @update:selected="handlePriceUpdate"
                    @close="closeModal"
                />
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
    const quickBarIds = quickBarCategories.value.map(cat => cat.id)
    return visibleCategories.value.filter(category => !quickBarIds.includes(category.id))
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

const handlePriceUpdate = (type, value) => {
    if (type === 'price') {
        priceRange.value = value
        
        // Dispatch the event in the format the search component expects
        window.dispatchEvent(new CustomEvent('filter-update', {
            detail: {
                type: 'price',
                value: value
            }
        }))
        
        // Update URL
        const params = new URLSearchParams(window.location.search)
        params.set('price0', value[0].toString())
        params.set('price1', value[1].toString())
        window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`)
    }
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
</script>
