<template>
    <teleport to="body">
        <div 
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click="handleBackgroundClick"
        >
            <div 
                class="bg-[#f4f3f3] w-full md:max-w-4xl md:mx-4 md:rounded-5xl shadow-xl flex flex-col h-screen md:h-[calc(100vh-10rem)] relative z-50 overflow-hidden"
                @click.stop
            >
                <!-- Header -->
                <div class="flex justify-center items-center p-8 flex-shrink-0">
                    <h2 class="text-2xl font-semibold">Filters</h2>
                    <button @click="$emit('close')" class="absolute right-8 items-center justify-center rounded-full bg-white border border-slate-400 hover:bg-black hover:fill-white">
                        <svg class="w-8 h-8">
                            <use xlink:href="/storage/website-files/icons.svg#ri-close-line" />
                        </svg>
                    </button>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="flex-1 p-8 flex justify-center items-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-black"></div>
                </div>

                <!-- Content when loaded -->
                <div v-else class="flex flex-col flex-1 overflow-hidden px-8 pb-8 space-y-4">
                    <!-- Categories Section -->
                    <div 
                        class="transition-all duration-300 ease-in-out bg-white rounded-4xl shadow-custom-3 flex flex-col"
                        :class="[
                            activeSection === 'categories' ? 'flex-1' : '',
                            'overflow-hidden'
                        ]"
                    >
                        <div 
                            @click="toggleSection('categories')"
                            class="flex items-center justify-between px-8 py-4 cursor-pointer flex-shrink-0"
                        >
                            <h3 class="text-1xl p-4 font-semibold">Categories</h3>
                            <div class="flex items-center gap-4">
                                <div v-if="activeSection === 'categories'" class="flex items-center gap-4">
                                    <div v-if="!isSearchingCategories">
                                        <button 
                                            @click.stop="toggleCategorySearch"
                                            class="p-2 rounded-full hover:bg-neutral-100 transition-colors"
                                        >
                                            <svg class="w-8 h-8 fill-black">
                                                <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-else class="border border-neutral-400 rounded-full flex items-center">
                                        <svg class="w-8 h-8 fill-black z-[1002] ml-8">
                                            <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                        </svg>
                                        <input 
                                            ref="categorySearchInput"
                                            v-model="categorySearchQuery"
                                            class="relative text-1xl p-4 w-[200px] font-bold z-40 bg-transparent focus:border-none placeholder-slate-400"
                                            placeholder="Search categories"
                                            @click.stop
                                            type="text"
                                        >
                                        <button
                                            @click.stop="() => {
                                                clearCategorySearch();
                                                isSearchingCategories.value = false;
                                            }"
                                            class="mr-8"
                                        >
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <span v-if="localSelected.categories.length" class="text-xl text-neutral-500">
                                    {{ localSelected.categories.length }} selected
                                </span>
                            </div>
                        </div>
                        
                        <div 
                            v-show="activeSection === 'categories'" 
                            class="px-8 pb-8 overflow-y-auto flex-1"
                        >
                            <div class="grid grid-cols-2 gap-4">
                                <button 
                                    v-for="category in filteredCategories" 
                                    :key="category.id"
                                    class="py-3 px-6 rounded-full text-left border transition-all overflow-hidden"
                                    :class="{ 
                                        'border-2 border-black bg-black text-white': isSelectedCategory(category.id),
                                        'border-neutral-300 hover:bg-neutral-50 text-neutral-800': !isSelectedCategory(category.id)
                                    }"
                                    @click="toggleCategory(category.id)"
                                >
                                    <span class="text-xl font-medium truncate block w-full">{{ category.name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tags Section -->
                    <div 
                        class="transition-all duration-300 ease-in-out bg-white rounded-4xl shadow-custom-3 flex flex-col"
                        :class="[
                            activeSection === 'tags' ? 'flex-1' : '',
                            'overflow-hidden'
                        ]"
                    >
                        <div 
                            @click="toggleSection('tags')"
                            class="flex items-center justify-between px-8 py-4 cursor-pointer flex-shrink-0"
                        >
                            <h3 class="text-1xl p-4 font-semibold">Tags</h3>
                            <div class="flex items-center gap-4">
                                <div v-if="activeSection === 'tags'" class="flex items-center gap-4">
                                    <div v-if="!isSearchingTags">
                                        <button 
                                            @click.stop="toggleTagSearch"
                                            class="p-2 rounded-full hover:bg-neutral-100 transition-colors"
                                        >
                                            <svg class="w-8 h-8 fill-black">
                                                <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-else class="border border-neutral-400 rounded-full flex items-center">
                                        <svg class="w-8 h-8 fill-black z-[1002] ml-8">
                                            <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                        </svg>
                                        <input 
                                            ref="tagSearchInput"
                                            v-model="tagSearchQuery"
                                            class="relative text-1xl p-4 w-[200px] font-bold z-40 bg-transparent focus:border-none placeholder-slate-400"
                                            placeholder="Search tags"
                                            @click.stop
                                            type="text"
                                        >
                                        <button
                                            @click.stop="() => {
                                                clearTagSearch();
                                                isSearchingTags.value = false;
                                            }"
                                            class="mr-8"
                                        >
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <span v-if="localSelected.tags.length" class="text-xl text-neutral-500">
                                    {{ localSelected.tags.length }} selected
                                </span>
                            </div>
                        </div>
                        
                        <div 
                            v-show="activeSection === 'tags'" 
                            class="px-8 pb-8 overflow-y-auto flex-1"
                        >
                            <div class="grid grid-cols-2 gap-4">
                                <button 
                                    v-for="tag in filteredTags" 
                                    :key="tag.id"
                                    class="py-3 px-6 rounded-full text-left border transition-all overflow-hidden"
                                    :class="{ 
                                        'border-2 border-black bg-black text-white': isSelectedTag(tag.id),
                                        'border-neutral-300 hover:bg-neutral-50 text-neutral-800': !isSelectedTag(tag.id)
                                    }"
                                    @click="toggleTag(tag.id)"
                                >
                                    <span class="text-xl font-medium truncate block w-full">{{ tag.name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Price Range Section -->
                    <div 
                        v-if="showPrice"
                        class="transition-all duration-300 ease-in-out bg-white rounded-4xl shadow-custom-3 flex flex-col"
                        :class="[
                            activeSection === 'price' ? 'flex-1' : '',
                            'overflow-hidden'
                        ]"
                    >
                        <div 
                            @click="toggleSection('price')"
                            class="flex items-center justify-between px-8 py-4 cursor-pointer flex-shrink-0"
                        >
                            <div>
                                <h3 class="text-1xl p-4 font-semibold">Price range</h3>
                            </div>
                            <div class="flex items-center gap-4">
                                <span v-if="localSelected.price[0] !== 0 || localSelected.price[1] !== maxPrice" class="text-xl text-neutral-500">
                                    {{ `$${localSelected.price[0]} - $${localSelected.price[1]}` }}
                                </span>
                            </div>
                        </div>
                        
                        <div v-show="activeSection === 'price'" class="px-12 pb-12">
                            <vue-slider
                                v-model="localSelected.price"
                                :min="0"
                                :max="maxPrice"
                                :tooltip="'none'"
                                :enable-cross="false"
                                :process-style="{ backgroundColor: '#000' }"
                                :rail-style="{ backgroundColor: '#e5e5e5' }"
                                :dot-style="{ 
                                    border: '2px solid black',
                                    backgroundColor: 'white',
                                    width: '24px',
                                    height: '24px',
                                    marginTop: '-.5rem'
                                }"
                                class="w-full mb-8"
                            />
                            <div class="flex justify-between">
                                <div class="space-y-2">
                                    <div class="text-lg font-medium text-neutral-600">Minimum</div>
                                    <div class="border border-neutral-300 rounded-full px-10 py-5">
                                        <div class="text-2xl">${{ localSelected.price[0] }}</div>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="text-lg font-medium text-neutral-600">Maximum</div>
                                    <div class="border border-neutral-300 rounded-full px-10 py-5">
                                        <div class="text-2xl">${{ localSelected.price[1] }}+</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-8 bg-white flex-shrink-0">
                    <div class="flex justify-between items-center">
                        <button 
                            @click="clearAll" 
                            class="underline text-neutral-600 hover:text-neutral-800"
                        >
                            Clear all
                        </button>
                        <div class="flex space-x-4">
                            <button 
                                @click="$emit('close')" 
                                class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl"
                            >
                                Cancel
                            </button>
                            <button 
                                @click="submitSelection" 
                                class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-neutral-800 text-xl"
                            >
                                Apply filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick, onBeforeUnmount } from 'vue'
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/antd.css'
import axios from 'axios'
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective'

const props = defineProps({
    priceRange: {
        type: Array,
        default: () => [0, 1000]
    },
    maxPrice: {
        type: Number,
        default: 1000
    },
    showPrice: {
        type: Boolean,
        default: false
    },
    selectedCategories: {
        type: Array,
        default: () => []
    },
    selectedTags: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['close', 'update:filters'])

// State
const loading = ref(true)
const activeTab = ref('categories')
const categories = ref([])
const tags = ref([])
const localSelected = ref({
    categories: [],
    tags: [],
    price: [...props.priceRange]
})

// Search state
const isSearchingCategories = ref(false)
const isSearchingTags = ref(false)
const categorySearchQuery = ref('')
const tagSearchQuery = ref('')
const categorySearchInput = ref(null)
const tagSearchInput = ref(null)

// Add new refs for the sorted lists
const sortedCategories = ref([])
const sortedTags = ref([])

// Computed
const availableTabs = computed(() => {
    const tabs = [
        { id: 'categories', name: 'Categories' },
        { id: 'tags', name: 'Tags' }
    ]
    if (props.showPrice) {
        tabs.push({ id: 'price', name: 'Price' })
    }
    return tabs
})

const hasChanges = computed(() => {
    // Compare local state against current prop values
    const categoriesChanged = JSON.stringify(localSelected.value.categories) !== JSON.stringify(props.selectedCategories)
    const tagsChanged = JSON.stringify(localSelected.value.tags) !== JSON.stringify(props.selectedTags)
    const priceChanged = JSON.stringify(localSelected.value.price) !== JSON.stringify(props.priceRange)

    // Check if any local values are different from the current filter state
    return categoriesChanged || tagsChanged || priceChanged
})

// Modify the computed properties to use the sorted lists but only filter
const filteredCategories = computed(() => {
    let filtered = sortedCategories.value
    if (categorySearchQuery.value) {
        const query = categorySearchQuery.value.toLowerCase()
        filtered = filtered.filter(cat => 
            cat.name.toLowerCase().includes(query)
        )
    }
    return filtered
})

const filteredTags = computed(() => {
    let filtered = sortedTags.value
    if (tagSearchQuery.value) {
        const query = tagSearchQuery.value.toLowerCase()
        filtered = filtered.filter(tag => 
            tag.name.toLowerCase().includes(query)
        )
    }
    return filtered
})

// Methods
const fetchFiltersData = async () => {
    try {
        const [categoriesResponse, genresResponse] = await Promise.all([
            axios.get('/api/categories/active/cached'),
            axios.get('/api/genres/active/cached')
        ])
        
        categories.value = categoriesResponse.data
        tags.value = genresResponse.data
        
        // Sort the lists after data is fetched
        sortLists()
        
        loading.value = false
    } catch (error) {
        console.error('Error fetching filters:', error)
        loading.value = false
    }
}

const isSelectedCategory = (id) => localSelected.value.categories.includes(id)
const isSelectedTag = (id) => localSelected.value.tags.includes(id)

const selectCategory = (categoryId) => {
    // For quickbar toggles, toggle single category
    const index = localSelected.value.categories.indexOf(categoryId);
    if (index === -1) {
        localSelected.value.categories = [...new Set([...localSelected.value.categories, categoryId])];
    } else {
        localSelected.value.categories = localSelected.value.categories.filter(id => id !== categoryId);
    }
}

const toggleCategory = (categoryId) => {
    selectCategory(categoryId);
}

const selectTag = (tagId) => {
    // Toggle tag selection
    const index = localSelected.value.tags.indexOf(tagId);
    if (index === -1) {
        localSelected.value.tags = [...new Set([...localSelected.value.tags, tagId])];
    } else {
        localSelected.value.tags = localSelected.value.tags.filter(id => id !== tagId);
    }
}

const toggleTag = (tagId) => {
    selectTag(tagId);
}

const sliderFormat = (v) => `$${('' + v).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`

const clearAll = () => {
    // Only clear the local state
    localSelected.value = {
        categories: [],
        tags: [],
        price: [0, props.maxPrice]
    }
}

const submitSelection = () => {
    // Always use the search page for filter redirects
    const searchPath = '/index/search'
    const params = new URLSearchParams(window.location.search)
    
    // Ensure searchType is always set
    if (!params.has('searchType')) {
        params.set('searchType', 'allEvents')
    }
    
    // Update categories
    if (localSelected.value.categories.length > 0) {
        params.set('category', localSelected.value.categories.join(','))
    } else {
        params.delete('category')
    }
    
    // Update tags
    if (localSelected.value.tags.length > 0) {
        params.set('tag', localSelected.value.tags.join(','))
    } else {
        params.delete('tag')
    }
    
    // Update price if it's being used
    if (props.showPrice && localSelected.value.price) {
        params.set('price0', localSelected.value.price[0].toString())
        params.set('price1', localSelected.value.price[1].toString())
    } else {
        params.delete('price0')
        params.delete('price1')
    }
    
    // Always redirect to the search page with the updated parameters
    window.location.href = `${searchPath}?${params.toString()}`
    
    // Close the modal
    emit('close')
}

// Add the image URL constant
const imageUrl = import.meta.env.VITE_IMAGE_URL

// Add the getCategoryIcon method
const getCategoryIcon = (category) => {
    return category.images?.find(img => img.rank === 1)?.thumb_image_path 
        ? `${imageUrl}${category.images.find(img => img.rank === 1).thumb_image_path}`
        : ''
}

// Initialize localSelected with URL parameters
const initializeFromUrl = () => {
    const params = new URLSearchParams(window.location.search)
    
    localSelected.value = {
        categories: params.get('category') ? params.get('category').split(',').map(Number) : props.selectedCategories,
        tags: params.get('tag') ? params.get('tag').split(',').map(Number) : props.selectedTags,
        price: [
            parseInt(params.get('price0') || props.priceRange[0]),
            parseInt(params.get('price1') || props.priceRange[1])
        ]
    }
}

// Add directive
const vClickOutside = ClickOutsideDirective

// Add these methods for scroll lock
const lockScroll = () => {
    document.body.style.overflow = 'hidden'
}

const unlockScroll = () => {
    document.body.style.overflow = ''
}

// Handle click outside
const handleBackgroundClick = (event) => {
    // Only close if clicking the background overlay
    if (event.target === event.currentTarget) {
        unlockScroll()
        emit('close')
    }
}

onMounted(() => {
    initializeFromUrl()
    fetchFiltersData()
    lockScroll()
})

onBeforeUnmount(() => {
    unlockScroll()
})

// Update the watch handlers to resort when props change
watch(() => props.selectedCategories, (newVal) => {
    localSelected.value.categories = [...newVal]
    sortLists() // Resort when selections change from props
})

watch(() => props.selectedTags, (newVal) => {
    localSelected.value.tags = [...newVal]
    sortLists() // Resort when selections change from props
})

watch(() => props.priceRange, (newVal) => {
    localSelected.value.price = [...newVal]
})

// Search methods
const toggleCategorySearch = () => {
    isSearchingCategories.value = true
    nextTick(() => {
        categorySearchInput.value?.focus()
    })
}

const toggleTagSearch = () => {
    isSearchingTags.value = true
    nextTick(() => {
        tagSearchInput.value?.focus()
    })
}

const clearCategorySearch = () => {
    categorySearchQuery.value = '';
    isSearchingCategories.value = false;
}

const clearTagSearch = () => {
    tagSearchQuery.value = '';
    isSearchingTags.value = false;
}

// Add new ref for active section
const activeSection = ref('categories')

// Add new method for toggling sections
const toggleSection = (section) => {
    activeSection.value = activeSection.value === section ? null : section
}

// Add a method to sort the lists
const sortLists = () => {
    sortedCategories.value = [...categories.value].sort((a, b) => {
        const aSelected = isSelectedCategory(a.id)
        const bSelected = isSelectedCategory(b.id)
        if (aSelected && !bSelected) return -1
        if (!aSelected && bSelected) return 1
        return 0
    })

    sortedTags.value = [...tags.value].sort((a, b) => {
        const aSelected = isSelectedTag(a.id)
        const bSelected = isSelectedTag(b.id)
        if (aSelected && !bSelected) return -1
        if (!aSelected && bSelected) return 1
        return 0
    })
}
</script>