<template>
    <teleport to="body">
        <div 
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click="handleBackgroundClick"
        >
            <div 
                class="bg-[#f4f3f3] w-full overflow-hidden md:max-w-4xl md:mx-4 md:rounded-5xl shadow-xl flex flex-col fixed inset-0 md:relative md:h-[calc(100vh-10rem)] z-50"
                @click.stop
            >
                <!-- Header -->
                <div class="flex justify-center items-center p-4 h-32 min-h-32 flex-shrink-0">
                    <h2 class="text-2xl font-semibold">Filters</h2>
                    <button 
                        @click="$emit('close')" 
                        class="absolute top-6 z-20 left-8 items-center justify-center rounded-full p-0 w-20 h-20 flex bg-white"
                    >
                        <svg class="w-12 h-12 text-red-500">
                            <use xlink:href="/storage/website-files/icons.svg#ri-close-line" />
                        </svg>
                    </button>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="flex-1 p-8 flex justify-center items-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-black"></div>
                </div>

                <!-- Content when loaded -->
                <div v-else class="flex flex-col flex-1 overflow-y-auto px-8 pb-8 space-y-4">
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
                            <h3 v-if="!isSearchingCategories" class="text-3xl p-4 font-semibold">Categories</h3>
                            <div class="flex items-center gap-4" :class="{'w-full': isSearchingCategories}">
                                <div v-if="activeSection === 'categories'" class="flex items-center gap-4" :class="{'w-full': isSearchingCategories}">
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
                                    <div v-else class="w-full border border-neutral-400 rounded-full flex items-center">
                                        <svg class="w-8 h-8 fill-black z-[1002] ml-8 flex-shrink-0">
                                            <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                        </svg>
                                        <input 
                                            ref="categorySearchInput"
                                            v-model="categorySearchQuery"
                                            class="relative text-3xl p-4 w-full font-bold z-40 bg-transparent focus:border-none focus:outline-none placeholder-slate-400 touch-manipulation"
                                            placeholder="Search categories"
                                            @click.stop
                                            autocomplete="off"
                                            type="text"
                                        >
                                        <button
                                            @click.stop="() => {
                                                clearCategorySearch();
                                                isSearchingCategories.value = false;
                                            }"
                                            class="mr-8 flex-shrink-0"
                                        >
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="selectedFilters.categories.length && !isSearchingCategories" class="bg-black rounded-full px-4 py-2">
                                    <span class="text-xl text-white">
                                        {{ selectedFilters.categories.length }} selected
                                    </span>
                                </div>
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
                                    <span class="text-1xl font-medium truncate block w-full">{{ category.name }}</span>
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
                            <h3 v-if="!isSearchingTags" class="text-3xl p-4 font-semibold">Tags</h3>
                            <div class="flex items-center gap-4" :class="{'w-full': isSearchingTags}">
                                <div v-if="activeSection === 'tags'" class="flex items-center gap-4" :class="{'w-full': isSearchingTags}">
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
                                    <div v-else class="w-full border border-neutral-400 rounded-full flex items-center">
                                        <svg class="w-8 h-8 fill-black z-[1002] ml-8 flex-shrink-0">
                                            <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                        </svg>
                                        <input 
                                            ref="tagSearchInput"
                                            v-model="tagSearchQuery"
                                            class="relative text-3xl p-4 w-full font-bold z-40 bg-transparent focus:border-none focus:outline-none placeholder-slate-400 touch-manipulation"
                                            placeholder="Search tags"
                                            @click.stop
                                            autocomplete="off"
                                            type="text"
                                        >
                                        <button
                                            @click.stop="() => {
                                                clearTagSearch();
                                                isSearchingTags.value = false;
                                            }"
                                            class="mr-8 flex-shrink-0"
                                        >
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="selectedFilters.tags.length && !isSearchingTags" class="bg-black rounded-full px-4 py-2">
                                    <span class="text-xl text-white">
                                        {{ selectedFilters.tags.length }} selected
                                    </span>
                                </div>
                                
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
                                    <span class="text-1xl font-medium truncate block w-full">{{ tag.name }}</span>
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
                                <h3 class="text-3xl p-4 font-semibold">Price range</h3>
                            </div>
                            <div class="flex items-center gap-4">
                                <div 
                                    v-if="(selectedFilters.price[0] !== 0 || selectedFilters.price[1] !== props.maxPrice) && activeSection !== 'price'" 
                                    class="bg-black rounded-full px-4 py-2"
                                >
                                    <span class="text-xl text-white">
                                        {{ `$${selectedFilters.price[0]} - $${selectedFilters.price[1]}` }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div v-show="activeSection === 'price'" class="px-12 pb-12">
                            <vue-slider
                                v-model="selectedFilters.price"
                                :min="0"
                                :max="selectedFilters.searchedMaxPrice"
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
                                        <div class="text-2xl">${{ selectedFilters.price[0] }}</div>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="text-lg font-medium text-neutral-600">Maximum</div>
                                    <div class="border border-neutral-300 rounded-full px-10 py-5">
                                        <div class="text-2xl">${{ selectedFilters.price[1] }}+</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="w-full bg-white p-8 flex-shrink-0 mt-auto">
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
import { ref, computed, onMounted, nextTick, onBeforeUnmount } from 'vue'
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/antd.css'
import axios from 'axios'
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective'

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
        default: () => ({
            categories: [],
            tags: [],
            price: [0, 1000]
        })
    },
    maxPrice: {
        type: Number,
        required: true
    },
    showPrice: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['close', 'update:modelValue', 'filter-change'])

// State
const loading = ref(true)
const categories = ref([])
const tags = ref([])
const selectedFilters = ref({ ...props.modelValue })

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

// Add new ref for active section
const activeSection = ref('categories')

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

const isSelectedCategory = (id) => selectedFilters.value.categories.includes(id)
const isSelectedTag = (id) => selectedFilters.value.tags.includes(id)

const selectCategory = (categoryId) => {
    const index = selectedFilters.value.categories.indexOf(categoryId);
    const newCategories = index === -1 
        ? [...new Set([...selectedFilters.value.categories, categoryId])]
        : selectedFilters.value.categories.filter(id => id !== categoryId);
    
    selectedFilters.value = {
        ...selectedFilters.value,
        categories: newCategories
    };
    
    sortLists();
}

const toggleCategory = (categoryId) => {
    selectCategory(categoryId);
}

const selectTag = (tagId) => {
    const index = selectedFilters.value.tags.indexOf(tagId);
    const newTags = index === -1 
        ? [...new Set([...selectedFilters.value.tags, tagId])]
        : selectedFilters.value.tags.filter(id => id !== tagId);
    
    selectedFilters.value = {
        ...selectedFilters.value,
        tags: newTags
    };
    
    sortLists();
}

const toggleTag = (tagId) => {
    selectTag(tagId);
}

const clearAll = () => {
    selectedFilters.value = {
        categories: [],
        tags: [],
        price: [0, props.maxPrice],
        searchedMaxPrice: selectedFilters.value.searchedMaxPrice
    };
    
    // Clear any search queries
    categorySearchQuery.value = ''
    tagSearchQuery.value = ''
    
    // Reset search states
    isSearchingCategories.value = false
    isSearchingTags.value = false
    
    // Resort the lists to reflect cleared state
    sortLists()
}

const submitSelection = () => {
    // Emit the final filter state when Apply is clicked
    emit('filter-change', selectedFilters.value);
    // Close the modal
    emit('close');
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

onMounted(() => {
    fetchFiltersData();
    lockScroll();
})

onBeforeUnmount(() => {
    unlockScroll()
})
</script>

<style>
/* Prevent zooming on focus for mobile devices */
input, select, textarea {
    font-size: 16px !important; /* Minimum font size to prevent zoom on iOS */
}

/* Add this to prevent zoom */
.touch-manipulation {
    touch-action: manipulation;
}

/* Make sure the placeholder is large enough too */
::placeholder {
    font-size: 16px !important;
}
</style>