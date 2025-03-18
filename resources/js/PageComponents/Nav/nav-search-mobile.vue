<template>
    <div class="w-full inline-block relative z-20">
        <div class="flex items-center justify-end pr-8 relative">
        </div>
        <template v-if="search">
            <div class="search-container fixed inset-0 z-20 bg-[#f4f3f3] flex flex-col">
                <div class="w-full h-32 min-h-32 flex justify-center items-center p-4">
                    <button 
                        @click.stop="hideSearch"
                        class="absolute top-6 z-20 left-8 items-center justify-center rounded-full p-0 w-20 h-20 flex bg-white">
                        <svg class="w-12 h-12 text-red-500">
                            <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                        </svg>
                    </button>
                    <button 
                        @click="search='l'"
                        :class="[
                            'text-gray-500 relative border-none p-4 text-2xl rounded-full transition-all duration-200',
                            search === 'l' ? 'font-bold' : 'font-normal',
                            'hover:bg-gray-100'
                        ]">
                        <span class="block font-bold invisible h-0">Location</span>
                        <span class="block" :class="{ 'font-bold text-black': search === 'l' }">Location</span>
                    </button>
                    <button 
                        @click="search='e'"
                        :class="[
                            'text-gray-500 relative border-none p-4 text-2xl rounded-full transition-all duration-200',
                            search === 'e' ? 'font-bold' : 'font-normal',
                            'hover:bg-gray-100'
                        ]">
                        <span class="block font-bold invisible h-0">Name</span>
                        <span class="block" :class="{ 'font-bold text-black': search === 'e' }">Name</span>
                    </button>
                </div>
                <div class="flex-grow overflow-y-auto px-6">
                    <div class="h-full">
                        <Transition name="fade" mode="out-in">
                            <div class="h-full" :key="search">
                                <SearchLocation 
                                    v-if="search==='l'" 
                                    ref="searchLocation"
                                    :initial-city="city"
                                    :initial-start-date="startDate"
                                    :initial-end-date="endDate"
                                    @update:location="handleLocationUpdate"
                                />
                                <SearchEvent v-if="search==='e'" class="h-full"/>
                            </div>
                        </Transition>
                    </div>
                </div>
                <div v-if="search === 'l'" class="w-full bg-white p-8 flex justify-between items-center mt-auto">
                    <div>
                        <button @click="handleClearAll" class="underline">Clear All</button>
                    </div>
                    <div>
                        <button 
                            @click="handleSearch"
                            :disabled="!city"
                            :class="[
                                'py-4 px-8 rounded-2xl flex gap-4',
                                city ? 'bg-[#ff385c] text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                            ]">
                            <svg class="w-8 h-8" :class="city ? 'fill-white' : 'fill-gray-500'">
                                <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                            </svg>
                            Search
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Default State -->
         
        <template v-else>
            <div class="w-full flex justify-between items-center gap-10 px-0">
                <button 
                    v-if="!isHomePage"
                    @click="handleBack"
                    class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors flex-shrink-0 z-[501]"
                >
                    <svg 
                        class="w-10 h-10" 
                        viewBox="0 0 24 24" 
                        fill="none" 
                        stroke="currentColor" 
                        stroke-width="2" 
                        stroke-linecap="round" 
                        stroke-linejoin="round"
                    >
                        <path d="M19 12H5"/>
                        <path d="M12 19l-7-7 7-7"/>
                    </svg>
                </button>
                <div class="relative flex-1">
                    <div
                        class="w-full absolute top-0 bottom-0 z-[500] cursor-pointer" 
                        @click="openSearch" 
                    />
                    <div class="w-full p-5 border rounded-full flex items-center shadow-custom-3">
                        <p class="w-full flex items-center justify-center text-center truncate">
                            <template v-if="city">
                                <span class="text-black text-1xl font-bold truncate max-w-[40%]">{{ city }}</span>
                                <span class="text-gray-300 mx-4">|</span>
                                <span class="text-black text-1xl truncate max-w-[40%]" :class="{ 'font-bold': startDate }">
                                    {{ startDate ? formatDateDisplay : 'Add dates' }}
                                </span>
                            </template>
                            <template v-else>
                                <svg class="w-6 h-6 fill-[#ff385c] mr-2">
                                    <use :xlink:href="`/storage/website-files/icons.svg#ri-search-line`" />
                                </svg>
                                <span class="text-black font-bold text-2xl">Search</span>
                            </template>
                        </p>
                    </div>
                </div>
                <button 
                    @click="openFilters"
                    class="w-[4.5rem] h-[4.5rem] flex-shrink-0 flex items-center justify-center rounded-full shadow-custom-3 transition-colors"
                    :class="[
                        hasActiveFilters 
                            ? 'bg-black hover:bg-gray-800' 
                            : 'hover:bg-gray-200'
                    ]"
                >
                    <svg 
                        xmlns="http://www.w3.org/2000/svg" 
                        viewBox="0 0 32 32" 
                        aria-hidden="true" 
                        role="presentation" 
                        focusable="false" 
                        style="display: block; fill: none; height: 16px; width: 16px; stroke-width: 2.5; overflow: visible;"
                        :style="{ stroke: hasActiveFilters ? 'white' : 'currentcolor' }"
                    >
                        <path fill="none" d="M7 16H3m26 0H15M29 6h-4m-8 0H3m26 20h-4M7 16a4 4 0 1 0 8 0 4 4 0 0 0-8 0zM17 6a4 4 0 1 0 8 0 4 4 0 0 0-8 0zm0 20a4 4 0 1 0 8 0 4 4 0 0 0-8 0zm0 0H3"></path>
                    </svg>
                </button>
            </div>
        </template>
    </div>
    <Filters
        v-if="showFilters"
        :show-price="true"
        :price-range="priceRange"
        :max-price="maxPrice"
        :selected-categories="selectedCategories"
        :selected-tags="selectedTags"
        @close="showFilters = false"
        @update:filters="handleFilterUpdate"
    />
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import SearchLocation from './Components/location-search-mobile.vue';
import SearchEvent from './Components/events-search-mobile.vue';
import Filters from './Components/filters.vue';
import eventStore from '@/Stores/EventStore';

// Reactive state
const search = ref(null);
const city = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const showFilters = ref(false);
const selectedCategories = ref([]);
const selectedTags = ref([]);
const priceRange = ref([0, 1000]);
const maxPrice = ref(1000);
const unsubscribe = ref(null);

// For template refs
const searchLocation = ref(null);

// Computed properties
const isHomePage = computed(() => {
    return window.location.pathname === '/';
});

const formatDateDisplay = computed(() => {
    if (!startDate.value) return '';

    const start = new Date(startDate.value);
    const end = endDate.value ? new Date(endDate.value) : null;
    
    // Format dates
    const formatDate = (date, isEndDate = false) => {
        const month = date.toLocaleDateString('en-US', { month: 'short' });
        const day = date.getDate();
        
        // If it's the end date and same month as start date, only return day
        if (isEndDate && end && start.getMonth() === end.getMonth()) {
            return day;
        }
        
        return `${month} ${day}`;
    };

    // If dates are the same, show only one date
    if (end && start.getTime() === end.getTime()) {
        return formatDate(start);
    } else if (end) {
        return `${formatDate(start)}-${formatDate(end, true)}`;
    } else {
        return formatDate(start);
    }
});

const hasActiveFilters = computed(() => {
    return (selectedCategories.value?.length > 0) || 
           (selectedTags.value?.length > 0) || 
           (priceRange.value?.[0] !== 0) || 
           (priceRange.value?.[1] !== maxPrice.value);
});

// Methods
const openSearch = () => {
    search.value = 'l';
    // Prevent background scrolling
    document.body.classList.add('overflow-hidden');
};

const hideSearch = () => {
    search.value = null;
    // Re-enable scrolling
    document.body.classList.remove('overflow-hidden');
};

const handleLocationUpdate = (value) => {
    if (typeof value === 'string') {
        // Just update the city name, don't touch the URL
        city.value = value;
    } else if (value && typeof value === 'object') {
        // Full location data - update EventStore
        eventStore.update({
            location: {
                city: value.city,
                lat: value.lat,
                lng: value.lng
            },
            dates: {
                start: value.start,
                end: value.end
            }
        }, false); // Don't fetch events yet until search is clicked
    } else {
        // Clear everything by updating EventStore
        eventStore.update({
            location: {
                city: null,
                lat: null,
                lng: null
            },
            dates: {
                start: null,
                end: null
            }
        }, false);
    }
};

const handleSearch = () => {
    // Use the current state in EventStore to perform search
    eventStore.fetchEvents();
    
    // Hide search modal after search
    hideSearch();
    
    // If we're not already on the search page, navigate there
    if (window.location.pathname !== '/index/search') {
        window.location.href = `/index/search?${new URLSearchParams(window.location.search).toString()}`;
    }
};

const handleClearAll = () => {
    // Clear state in EventStore
    eventStore.update({
        location: {
            city: null,
            lat: null,
            lng: null
        },
        dates: {
            start: null,
            end: null
        }
    }, false);
    
    // Reset local state
    city.value = null;
    startDate.value = null;
    endDate.value = null;
    
    // Tell child component to clear its state
    if (searchLocation.value) {
        searchLocation.value.clearState(true);
    }
};

const handleFilterUpdate = (filters) => {
    // Update EventStore with the new filters
    eventStore.update({
        filters: {
            categories: filters.categories,
            tags: filters.tags,
            price: filters.price
        }
    });
    
    // Hide filters after updating
    showFilters.value = false;
};

const openFilters = () => {
    showFilters.value = true;
};

const handleBack = () => {
    window.history.back();
};

// Lifecycle hooks
onMounted(() => {
    // Subscribe to EventStore state changes
    unsubscribe.value = eventStore.subscribe(state => {
        // Update local state from the store
        city.value = state.location.city;
        startDate.value = state.dates.start;
        endDate.value = state.dates.end;
        selectedCategories.value = state.filters.categories;
        selectedTags.value = state.filters.tags;
        priceRange.value = state.filters.price;
    });
    
    // Listen for max price updates
    window.addEventListener('max-price-update', (event) => {
        maxPrice.value = event.detail || 1000;
    });
});

onUnmounted(() => {
    // Clean up subscription
    if (unsubscribe.value) {
        unsubscribe.value();
    }
});
</script>

<style>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Add global styles that will be applied to the body */
:global(.overflow-hidden) {
    overflow: hidden;
    position: fixed;
    width: 100%;
    height: 100%;
}
</style>
