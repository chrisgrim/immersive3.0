<template>
    <teleport to="body">
        <div 
            class="fixed inset-0 bg-black bg-opacity-50 flex flex-col z-50"
            @click="handleBackgroundClick"
        >
            <div 
                class="bg-white w-full h-[85vh] overflow-hidden flex flex-col fixed inset-x-0 bottom-0 rounded-t-5xl z-50 safe-bottom"
                @click.stop
            >


                <!-- Header -->
                <div class="flex justify-center items-center px-6 h-20 flex-shrink-0 border-b border-neutral-200">
                    <h2 class="text-3xl font-semibold">Filters</h2>
                    <button 
                        @click="$emit('close')" 
                        class="absolute top-3 right-6 p-2"
                    >
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="flex-1 p-8 flex justify-center items-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-black"></div>
                </div>

                <!-- Content when loaded -->
                <div v-else class="flex-1 overflow-y-auto px-10">
                    <!-- Remote Toggle Section -->
                    <div class="transition-all duration-300 ease-in-out bg-white border-b flex flex-col py-8">
                        <div class="flex items-center justify-between">
                            <p class="text-4xl font-semibold">Remote Events</p>
                            <div class="pr-4">
                                <toggle-switch
                                    v-model="selectedFilters.atHome"
                                    left-label="All"
                                    right-label="Remote"
                                    text-size="sm"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Price Range Section - Always visible -->
                    <div v-if="showPrice" class="border-b border-neutral-200 py-8">
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-4xl font-semibold">Price Range</p>
                            <span 
                                v-if="selectedFilters.price[0] !== 0 || selectedFilters.price[1] !== selectedFilters.maxPrice" 
                                class="bg-black text-white text-2xl rounded-full px-4 py-2"
                            >
                                ${{ selectedFilters.price[0] }} - ${{ selectedFilters.price[1] }}{{ selectedFilters.price[1] === selectedFilters.maxPrice ? '+' : '' }}
                            </span>
                        </div>
                        <div class="px-10 pt-10 pb-2">
                            <vue-slider
                                v-if="selectedFilters.maxPrice > 0"
                                v-model="selectedFilters.price"
                                :min="0"
                                :max="selectedFilters.maxPrice"
                                :tooltip="'none'"
                                :enable-cross="false"
                                :process-style="{ backgroundColor: '#f7653b' }"
                                :rail-style="{ backgroundColor: '#e5e5e5' }"
                                :dot-style="{ 
                                    border: '2px solid black',
                                    backgroundColor: 'white',
                                    width: '30px',
                                    height: '30px',
                                    margin: '-8px 0 0 0',
                                    boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
                                }"
                                class="w-full mb-8 mt-4"
                            />
                        </div>
                        <div class="flex justify-between px-8">
                            <div class="space-y-1">
                                <div class="text-sm text-neutral-600">Min</div>
                                <div class="border border-neutral-300 rounded-full px-4 py-2">
                                    <div class="text-2xl">${{ selectedFilters.price[0] }}</div>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <div class="text-sm text-neutral-600">Max</div>
                                <div class="border border-neutral-300 rounded-full px-4 py-2">
                                    <div class="text-2xl">
                                        ${{ selectedFilters.price[1] }}{{ selectedFilters.price[1] === selectedFilters.maxPrice ? '+' : '' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Categories Section -->
                    <div class="border-b border-neutral-200">
                        <div 
                            @click="toggleSection('categories')"
                            class="flex items-center justify-between cursor-pointer flex-shrink-0 py-8"
                        >
                            <p v-if="!isSearchingCategories" class="text-4xl font-semibold">Categories</p>
                            <div class="flex items-center gap-4" :class="{'w-full': isSearchingCategories}">
                                <div v-if="activeSection === 'categories'" class="flex items-center gap-4" :class="{'w-full': isSearchingCategories}">
                                    <div v-if="!isSearchingCategories">
                                        <button 
                                            @click.stop="toggleCategorySearch"
                                            class="p-2 rounded-full hover:bg-neutral-100 transition-colors"
                                        >
                                            <svg class="w-10 h-10 fill-black">
                                                <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-else class="w-full border border-neutral-300 rounded-full flex items-center">
                                        <svg class="w-10 h-10 fill-black z-[1002] ml-4 flex-shrink-0">
                                            <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                        </svg>
                                        <input 
                                            ref="categorySearchInput"
                                            v-model="categorySearchQuery"
                                            class="relative text-4xl! p-2 w-full z-40 bg-transparent focus:border-none focus:outline-none placeholder-slate-400 touch-manipulation"
                                            placeholder="Search categories"
                                            @click.stop
                                            autocomplete="off"
                                            type="text"
                                        >
                                        <button
                                            @click.stop="() => {
                                                clearCategorySearch();
                                                isSearchingCategories = false;
                                            }"
                                            class="mr-4 flex-shrink-0"
                                        >
                                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="selectedFilters.categories.length && !isSearchingCategories" class="bg-black rounded-full px-4 py-2">
                                    <span class="text-2xl text-white">
                                        {{ selectedFilters.categories.length }} selected
                                    </span>
                                </div>
                                <!-- Add chevron icon that indicates section state -->
                                <svg 
                                    v-if="activeSection !== 'categories' && !isSearchingCategories" 
                                    class="w-10 h-10 text-gray-600 ml-2" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                                <svg 
                                    v-else-if="!isSearchingCategories" 
                                    class="w-10 h-10 text-gray-600 ml-2" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                </svg>
                            </div>
                        </div>
                        
                        <div 
                            v-show="activeSection === 'categories'" 
                            class="pb-6"
                        >
                            <!-- Categories list -->
                            <div class="grid grid-cols-2 gap-3">
                                <button 
                                    v-for="category in filteredCategories" 
                                    :key="category.id"
                                    class="py-3 px-4 text-left flex items-center gap-3 hover:bg-neutral-50 transition-all"
                                    @click="toggleCategory(category.id)"
                                >
                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg border transition-colors flex items-center justify-center"
                                         :class="{ 
                                            'border-black bg-neutral-800': isSelectedCategory(category.id),
                                            'border-neutral-300': !isSelectedCategory(category.id)
                                         }"
                                    >
                                        <svg v-if="isSelectedCategory(category.id)" class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <span class="text-2xl">{{ category.name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tags Section -->
                    <div class="border-b border-neutral-200">
                        <div 
                            @click="toggleSection('tags')"
                            class="flex items-center justify-between cursor-pointer flex-shrink-0 py-8"
                        >
                            <p v-if="!isSearchingTags" class="text-4xl font-semibold">Tags</p>
                            <div class="flex items-center gap-4" :class="{'w-full': isSearchingTags}">
                                <div v-if="activeSection === 'tags'" class="flex items-center gap-4" :class="{'w-full': isSearchingTags}">
                                    <div v-if="!isSearchingTags">
                                        <button 
                                            @click.stop="toggleTagSearch"
                                            class="p-2 rounded-full hover:bg-neutral-100 transition-colors"
                                        >
                                            <svg class="w-10 h-10 fill-black">
                                                <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-else class="w-full border border-neutral-300 rounded-full flex items-center">
                                        <svg class="w-10 h-10 fill-black z-[1002] ml-4 flex-shrink-0">
                                            <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                        </svg>
                                        <input 
                                            ref="tagSearchInput"
                                            v-model="tagSearchQuery"
                                            class="relative text-4xl p-2 w-full z-40 bg-transparent focus:border-none focus:outline-none placeholder-slate-400 touch-manipulation"
                                            placeholder="Search tags"
                                            @click.stop
                                            autocomplete="off"
                                            type="text"
                                        >
                                        <button
                                            @click.stop="() => {
                                                clearTagSearch();
                                                isSearchingTags = false;
                                            }"
                                            class="mr-4 flex-shrink-0"
                                        >
                                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="selectedFilters.tags.length && !isSearchingTags" class="bg-black rounded-full px-4 py-2">
                                    <span class="text-2xl text-white">
                                        {{ selectedFilters.tags.length }} selected
                                    </span>
                                </div>
                                <!-- Add chevron icon that indicates section state -->
                                <svg 
                                    v-if="activeSection !== 'tags' && !isSearchingTags" 
                                    class="w-10 h-10 text-gray-600 ml-2" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                                <svg 
                                    v-else-if="!isSearchingTags" 
                                    class="w-10 h-10 text-gray-600 ml-2" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                </svg>
                            </div>
                        </div>
                        
                        <div 
                            v-show="activeSection === 'tags'" 
                            class="pb-6"
                        >
                            <!-- Tags list -->
                            <div class="grid grid-cols-2 gap-3">
                                <button 
                                    v-for="tag in filteredTags" 
                                    :key="tag.id"
                                    class="py-3 px-4 text-left flex items-center gap-3 hover:bg-neutral-50 transition-all"
                                    @click="toggleTag(tag.id)"
                                >
                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg border transition-colors flex items-center justify-center"
                                         :class="{ 
                                            'border-black bg-black': isSelectedTag(tag.id),
                                            'border-neutral-300': !isSelectedTag(tag.id)
                                         }"
                                    >
                                        <svg v-if="isSelectedTag(tag.id)" class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <span class="text-2xl">{{ tag.name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-white p-4 flex-shrink-0 border-t border-neutral-200 flex items-center justify-between sticky bottom-0">
                    <button 
                        @click="clearAll" 
                        class="font-semibold text-neutral-600 py-3 px-4"
                    >
                        Clear all
                    </button>
                    <button 
                        @click="submitSelection" 
                        class="px-6 py-3 bg-black text-white rounded-xl font-medium w-32"
                    >
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, onBeforeUnmount, watch } from 'vue'
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/antd.css'
import axios from 'axios'
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue'

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
        default: () => ({
            categories: [],
            tags: [],
            price: [0, null],
            atHome: false
        })
    },
    showPrice: {
        type: Boolean,
        default: false
    },
    maxPrice: {
        type: Number,
        default: null
    }
})

const emit = defineEmits(['close', 'update:modelValue', 'filter-change'])

// State
const loading = ref(true)
const categories = ref([])
const tags = ref([])
const selectedFilters = ref({
    ...props.modelValue,
    maxPrice: props.maxPrice || props.modelValue.maxPrice || 0
})

// Search state
const isSearchingCategories = ref(false)
const isSearchingTags = ref(false)
const categorySearchQuery = ref('')
const tagSearchQuery = ref('')
const categorySearchInput = ref(null)
const tagSearchInput = ref(null)

// Sorted lists
const sortedCategories = ref([])
const sortedTags = ref([])

// Active section
const activeSection = ref('')

// Computed properties
const filteredCategories = computed(() => {
    let filtered = sortedCategories.value
    
    // Filter categories based on attendance type
    if (selectedFilters.value.atHome) {
        // For remote events (atHome), only show categories applicable to attendance_type_id 2
        filtered = filtered.filter(cat => 
            !cat.applicable_attendance_types || 
            cat.applicable_attendance_types.includes(2)
        )
    } else {
        // For in-person events, only show categories applicable to attendance_type_id 1
        filtered = filtered.filter(cat => 
            !cat.applicable_attendance_types || 
            cat.applicable_attendance_types.includes(1)
        )
    }
    
    // Then apply search query filter
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

// Methods for search functionality
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
    categorySearchQuery.value = ''
    isSearchingCategories.value = false
}

const clearTagSearch = () => {
    tagSearchQuery.value = ''
    isSearchingTags.value = false
}

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

const toggleCategory = (categoryId) => {
    const index = selectedFilters.value.categories.indexOf(categoryId)
    const newCategories = index === -1 
        ? [...new Set([...selectedFilters.value.categories, categoryId])]
        : selectedFilters.value.categories.filter(id => id !== categoryId)
    
    selectedFilters.value = {
        ...selectedFilters.value,
        categories: newCategories
    }
    
    // Remove the re-sorting after selection
}

const toggleTag = (tagId) => {
    const index = selectedFilters.value.tags.indexOf(tagId)
    const newTags = index === -1 
        ? [...new Set([...selectedFilters.value.tags, tagId])]
        : selectedFilters.value.tags.filter(id => id !== tagId)
    
    selectedFilters.value = {
        ...selectedFilters.value,
        tags: newTags
    }
    
    // Remove the re-sorting after selection
}

const submitSelection = () => {
    // Check if price slider has been adjusted from default values
    const isPriceAdjusted = selectedFilters.value.price[0] !== 0 || 
                          selectedFilters.value.price[1] !== selectedFilters.value.maxPrice;
    
    selectedFilters.value = {
        ...selectedFilters.value,
        searchingByPrice: isPriceAdjusted,
        price: isPriceAdjusted 
            ? selectedFilters.value.price 
            : [0, selectedFilters.value.maxPrice]
    };
    
    // Emit the final filter state when Apply is clicked
    emit('filter-change', selectedFilters.value);
    // Close the modal
    emit('close');
}

const clearAll = () => {
    selectedFilters.value = {
        categories: [],
        tags: [],
        price: [0, selectedFilters.value.maxPrice],
        searchingByPrice: false,  // Reset the searching by price flag
        atHome: false,  // Reset atHome to false
        maxPrice: selectedFilters.value.maxPrice  // Preserve maxPrice
    };
    
    // Clear any search queries
    categorySearchQuery.value = '';
    tagSearchQuery.value = '';
    
    // We still want to sort after clearing all
    sortLists();
}

// Scroll lock
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

// Toggle section
const toggleSection = (section) => {
    // Always reset search states when toggling sections
    isSearchingCategories.value = false;
    isSearchingTags.value = false;
    categorySearchQuery.value = '';
    tagSearchQuery.value = '';
    
    activeSection.value = activeSection.value === section ? null : section;
}

// Sort lists
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

// Add watch effect for props.maxPrice changes
watch(() => props.maxPrice, (newMaxPrice) => {
    if (newMaxPrice && (!selectedFilters.value.maxPrice || selectedFilters.value.maxPrice < newMaxPrice)) {
        selectedFilters.value.maxPrice = newMaxPrice;
    }
}, { immediate: true });

// Add watch effect for modelValue changes
watch(() => props.modelValue, (newValue) => {
    if (newValue.maxPrice && (!selectedFilters.value.maxPrice || selectedFilters.value.maxPrice < newValue.maxPrice)) {
        selectedFilters.value.maxPrice = newValue.maxPrice;
    }
}, { deep: true });

// Add a watch for atHome changes
watch(() => selectedFilters.value.atHome, (newValue) => {
    // When attendance type changes, filter out categories that aren't applicable
    if (selectedFilters.value.categories.length) {
        // Get all applicable categories for the current mode
        const applicableCategories = categories.value.filter(cat => {
            if (!cat.applicable_attendance_types) return true; // No restrictions
            return newValue 
                ? cat.applicable_attendance_types.includes(2) // Remote
                : cat.applicable_attendance_types.includes(1); // In-person
        });
        
        // Filter selected categories to only include applicable ones
        const applicableCategoryIds = applicableCategories.map(cat => cat.id);
        selectedFilters.value.categories = selectedFilters.value.categories.filter(
            id => applicableCategoryIds.includes(id)
        );
    }
    
    // Resort the lists to reflect the new state
    sortLists();
});

// Add a computed property for hasActiveFilters
const hasActiveFilters = computed(() => {
    // Check for categories, tags and atHome
    const hasActiveFilter = 
        selectedFilters.value.categories?.length > 0 || 
        selectedFilters.value.tags?.length > 0 || 
        selectedFilters.value.atHome === true;
    
    // Check price filters
    const hasPriceFilter = selectedFilters.value.price[0] !== 0 || 
                          selectedFilters.value.price[1] !== selectedFilters.value.maxPrice;
    
    return hasActiveFilter || hasPriceFilter;
});

// Expose hasActiveFilters for parent components
defineExpose({ hasActiveFilters });
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

/* Prevent text selection when tapping on tags/categories */
.truncate {
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    user-select: none;
}

/* Safe area padding for iOS devices */
.safe-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0);
}
</style>
