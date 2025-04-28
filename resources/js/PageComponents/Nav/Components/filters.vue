<template>
    <teleport to="body">
        <div 
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click="handleBackgroundClick"
        >
            <div 
                class="bg-white w-full overflow-hidden md:max-w-4xl md:mx-4 md:rounded-5xl shadow-xl flex flex-col fixed inset-0 md:relative md:h-[calc(100vh-10rem)] z-50"
                @click.stop
            >
                <!-- Header -->
                <div class="flex justify-center items-center p-4 h-24 min-h-24 flex-shrink-0 border-b border-neutral-200">
                    <p class="text-2xl font-bold">Filters</p>
                    <button 
                        @click="$emit('close')" 
                        class="absolute top-4 z-20 right-8 items-center justify-center rounded-full p-0 w-16 h-16 flex bg-white"
                    >
                        <svg class="w-8 h-8 text-red-500">
                            <use xlink:href="/storage/website-files/icons.svg#ri-close-line" />
                        </svg>
                    </button>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="flex-1 p-8 flex justify-center items-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-black"></div>
                </div>

                <!-- Content when loaded -->
                <div v-else class="flex-1 overflow-y-auto">
                    <div class="flex flex-col px-8 pb-8">

                        <!-- Remote Toggle Section -->
                        <div class="transition-all duration-300 ease-in-out bg-white border-b flex flex-col py-8">
                            <div class="flex items-center justify-between">
                                <p class="text-3xl px-4 font-semibold">Remote Events</p>
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

                        <!-- Price Range Section -->
                        <div 
                            v-if="showPrice"
                            class="transition-all duration-300 ease-in-out flex-1 bg-white border-b flex flex-col"
                        >
                            <div 
                                class="flex items-center justify-between py-4 cursor-pointer flex-shrink-0"
                            >
                                <div>
                                    <p class="text-3xl p-4 font-semibold">Price range</p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div 
                                        v-if="(selectedFilters.price[0] !== 0 || selectedFilters.price[1] !== selectedFilters.maxPrice) && activeSection !== 'price'" 
                                        class="bg-black rounded-full px-4 py-2"
                                    >
                                        <span class="text-xl text-white">
                                            {{ `$${selectedFilters.price[0]} - $${selectedFilters.price[1]}` }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="px-12 pb-12">
                                <vue-slider
                                    v-if="selectedFilters.maxPrice > 0"
                                    v-model="selectedFilters.price"
                                    :min="0"
                                    :max="selectedFilters.maxPrice"
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
                                            <div class="text-2xl">
                                                ${{ selectedFilters.price[1] }}{{ selectedFilters.price[1] === selectedFilters.maxPrice ? '+' : '' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Categories Section -->
                        <div 
                            class="transition-all duration-300 ease-in-out bg-white border-b flex flex-col"
                            :class="[
                                activeSection === 'categories' ? 'flex-1' : '',
                            ]"
                        >
                            <div 
                                @click="toggleSection('categories')"
                                class="flex items-center justify-between py-8 cursor-pointer flex-shrink-0"
                            >
                                <p v-if="!isSearchingCategories" class="text-3xl p-4 font-semibold">Categories</p>
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
                                    <svg 
                                        v-if="activeSection !== 'categories' && !isSearchingCategories" 
                                        class="w-8 h-8 text-gray-600 ml-2" 
                                        fill="none" 
                                        stroke="currentColor" 
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                    <svg 
                                        v-else-if="!isSearchingCategories" 
                                        class="w-8 h-8 text-gray-600 ml-2" 
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
                                class="pb-8"
                            >
                                <div class="grid grid-cols-2 gap-4">
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
                                        <span class="text-1xl truncate">{{ category.name }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Tags Section -->
                        <div 
                            class="transition-all duration-300 ease-in-out bg-white border-b flex flex-col"
                            :class="[
                                activeSection === 'tags' ? 'flex-1' : '',
                            ]"
                        >
                            <div 
                                @click="toggleSection('tags')"
                                class="flex items-center justify-between py-8 cursor-pointer flex-shrink-0"
                            >
                                <p v-if="!isSearchingTags" class="text-3xl p-4 font-semibold">Tags</p>
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
                                    
                                    <!-- Add chevron icon that indicates section state -->
                                    <svg 
                                        v-if="activeSection !== 'tags' && !isSearchingTags" 
                                        class="w-8 h-8 text-gray-600 ml-2" 
                                        fill="none" 
                                        stroke="currentColor" 
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                    <svg 
                                        v-else-if="!isSearchingTags" 
                                        class="w-8 h-8 text-gray-600 ml-2" 
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
                                class="px-8 pb-8"
                            >
                                <div class="grid grid-cols-2 gap-4">
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
                                        <span class="text-1xl truncate">{{ tag.name }}</span>
                                    </button>
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
import { ref, computed, onMounted, nextTick, onBeforeUnmount, watch } from 'vue'
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/antd.css'
import axios from 'axios'
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective'
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
    }
})

const emit = defineEmits(['close', 'update:modelValue', 'filter-change'])

// State
const loading = ref(true)
const categories = ref([])
const tags = ref([])
const selectedFilters = ref({
    ...props.modelValue
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

// Add new ref for active section
const activeSection = ref('')

// Modify the computed properties to use the sorted lists but only filter
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
}

const toggleTag = (tagId) => {
    selectTag(tagId);
}

const submitSelection = () => {
    // Check if price slider has been adjusted from default values
    const isPriceAdjusted = selectedFilters.value.price[0] !== 0 || 
                           selectedFilters.value.price[1] !== props.maxPrice;
    
    selectedFilters.value = {
        ...selectedFilters.value,
        searchingByPrice: isPriceAdjusted,
        price: isPriceAdjusted 
            ? selectedFilters.value.price 
            : [0, props.maxPrice]
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
        price: [0, props.maxPrice],
        searchingByPrice: false,  // Reset the searching by price flag
        atHome: false  // Reset atHome to false
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
})

onMounted(() => {
    fetchFiltersData();
    lockScroll();
})

onBeforeUnmount(() => {
    unlockScroll()
})

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

/* Make sure the placeholder is large enough too */
::placeholder {
    font-size: 16px !important;
}
</style>