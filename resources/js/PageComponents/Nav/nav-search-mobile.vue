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
                                    :initial-city="state.location.city"
                                    :initial-start-date="state.dates.start"
                                    :initial-end-date="state.dates.end"
                                    @update:location="handleLocationUpdate"
                                    @search="handleSearch"
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
                            :disabled="!state.location.city"
                            :class="[
                                'py-4 px-8 rounded-2xl flex gap-4',
                                state.location.city ? 'bg-[#ff385c] text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                            ]">
                            <svg class="w-8 h-8" :class="state.location.city ? 'fill-white' : 'fill-gray-500'">
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
                            <template v-if="state.location.city">
                                <span class="text-black text-1xl font-bold truncate max-w-[40%]">{{ state.location.city }}</span>
                                <span class="text-gray-300 mx-4">|</span>
                                <span class="text-black text-1xl truncate max-w-[40%]" :class="{ 'font-bold': state.dates.start }">
                                    {{ state.dates.start ? formatDateDisplay : 'Add dates' }}
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
        v-model="state.filters"
        :max-price="maxPrice"
        @close="showFilters = false"
        @filter-change="handleFilterUpdate"
    />
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import SearchLocation from './Components/location-search-mobile.vue';
import SearchEvent from './Components/events-search-mobile.vue';
import Filters from './Components/filters.vue';
import SearchStore from '@/Stores/SearchStore.vue';
import MapStore from '@/Stores/MapStore.vue';
import axios from 'axios';

// Props definition
const props = defineProps({
    searchedEvents: {
        type: Object,
        default: () => ({})
    },
    maxPrice: {
        type: Number,
        default: 1000
    }
});

// Replace the state ref with a computed property that uses SearchStore
const state = computed(() => SearchStore.state);

// UI state refs (just what the UI needs, not the search data)
const search = ref(null);
const showFilters = ref(false);
const unsubscribe = ref(null);
const searchLocation = ref(null);

// Utility functions
const getUrlParams = () => new URLSearchParams(window.location.search);

const updateUrlParams = (params) => {
    window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`);
};

const debounce = (fn, delay) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
};

// Computed properties
const isHomePage = computed(() => {
    return window.location.pathname === '/';
});

const formatDateDisplay = computed(() => {
    if (!state.value.dates.start) return '';

    const start = new Date(state.value.dates.start);
    const end = state.value.dates.end ? new Date(state.value.dates.end) : null;
    
    const formatDate = (date, isEndDate = false) => {
        const month = date.toLocaleDateString('en-US', { month: 'short' });
        const day = date.getDate();
        
        if (isEndDate && end && start.getMonth() === end.getMonth()) {
            return day;
        }
        
        return `${month} ${day}`;
    };

    if (end && start.getTime() === end.getTime()) {
        return formatDate(start);
    } else if (end) {
        return `${formatDate(start)}-${formatDate(end, true)}`;
    } else {
        return formatDate(start);
    }
});

const hasActiveFilters = computed(() => {
    const isSearchPage = window.location.pathname.includes('/index/search');
    
    return (state.value.filters.categories?.length > 0) || 
           (state.value.filters.tags?.length > 0) || 
           (isSearchPage && (
               state.value.filters.price?.[0] !== 0 || 
               state.value.filters.price?.[1] !== state.value.maxPrice
           ));
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
        // Just update the city name in the store
        SearchStore.updateState({
            location: {
                city: value
            }
        });
    } else if (value && typeof value === 'object') {
        // Update SearchStore with location and dates
        SearchStore.updateState({
            location: {
                city: value.city,
                lat: value.lat !== undefined ? value.lat : null,
                lng: value.lng !== undefined ? value.lng : null,
                searchType: 'inPerson',
                live: false
            },
            dates: {
                start: value.start,
                end: value.end
            }
        });
    } else {
        // Clear location and dates in the store
        SearchStore.updateState({
            location: {
                city: null,
                lat: null,
                lng: null
            },
            dates: {
                start: null,
                end: null
            }
        });
    }
};

const handleSearch = () => {
    const params = new URLSearchParams(window.location.search);
    
    // Add location parameters if we have a city
    if (state.value.location.city) {
        params.set('city', state.value.location.city);
        
        // Only set coordinates if they're actually present and valid
        if (state.value.location.lat !== null && 
            state.value.location.lng !== null &&
            !isNaN(state.value.location.lat) && 
            !isNaN(state.value.location.lng)) {
            params.set('lat', state.value.location.lat);
            params.set('lng', state.value.location.lng);
        } else {
            console.error('Invalid coordinates for', state.value.location.city, 
                        'lat:', state.value.location.lat, 
                        'lng:', state.value.location.lng);
        }
        
        params.set('searchType', 'inPerson');
        params.set('live', 'false');
    }
    
    // Add date parameters if they exist
    if (state.value.dates.start) {
        params.set('start', state.value.dates.start);
        params.set('end', state.value.dates.end || state.value.dates.start);
    }
    
    // Add existing filter parameters
    if (state.value.filters.categories.length) {
        params.set('category', state.value.filters.categories.join(','));
    }
    
    if (state.value.filters.tags.length) {
        params.set('tag', state.value.filters.tags.join(','));
    }
    
    const [minPrice, maxPrice] = state.value.filters.price;
    if (minPrice > 0) {
        params.set('price0', minPrice);
    }
    if (maxPrice < state.value.maxPrice) {
        params.set('price1', maxPrice);
    }
    
    // Hide search modal after search
    hideSearch();
    
    // Check if we're changing city or location
    const currentCity = new URLSearchParams(window.location.search).get('city');
    const isNewLocation = currentCity !== state.value.location.city;
    
    if (isNewLocation) {
        // If it's a new location, remove any existing map boundary parameters
        params.delete('NElat');
        params.delete('NElng');
        params.delete('SWlat');
        params.delete('SWlng');
    }
    
    // Navigate to the search page
    window.location.href = `/index/search?${params.toString()}`;
};

const handleClearAll = () => {
    // Clear location and dates in the store
    SearchStore.updateState({
        location: {
            city: null,
            lat: null,
            lng: null
        },
        dates: {
            start: null,
            end: null
        }
    });
    
    // Tell child component to clear its state
    if (searchLocation.value) {
        searchLocation.value.clearState(true);
    }
};

const handleFilterUpdate = (filters) => {
    // Update SearchStore with the new filters
    SearchStore.updateState({
        filters: filters
    });
    
    // Hide filters after updating
    showFilters.value = false;
    
    // If we're on search page, update URL and fetch results
    if (window.location.pathname.includes('/index/search')) {
        const params = getUrlParams();
        
        // Reset to page 1 when filters change
        params.set('page', 1);
        
        if (filters.categories.length) {
            params.set('category', filters.categories.join(','));
        } else {
            params.delete('category');
        }
        
        if (filters.tags.length) {
            params.set('tag', filters.tags.join(','));
        } else {
            params.delete('tag');
        }

        const [minPrice, maxPrice] = filters.price;
        if (minPrice > 0) {
            params.set('price0', minPrice);
        } else {
            params.delete('price0');
        }
        if (maxPrice < state.value.maxPrice) {
            params.set('price1', maxPrice);
        } else {
            params.delete('price1');
        }

        updateUrlParams(params);
        fetchResults(params.toString());
    }
};

const handleBack = () => {
    window.history.back();
};

// Fetch results (simplified to use SearchStore)
const fetchResults = async (queryString) => {
    try {
        await SearchStore.fetchResults(queryString);
    } catch (error) {
        console.error('Error in fetchResults:', error);
    }
};

// Set up subscription to MapStore for map-based searches
const subscribeToMapStore = () => {
    unsubscribe.value = MapStore.subscribe((mapState) => {
        const params = getUrlParams();
        
        // Set all map boundary and search parameters
        ['NElat', 'NElng', 'SWlat', 'SWlng'].forEach((param, i) => {
            const value = i < 2 
                ? mapState.bounds.northEast[param.slice(2).toLowerCase()]
                : mapState.bounds.southWest[param.slice(2).toLowerCase()];
            params.set(param, parseFloat(value).toFixed(6));
        });
        
        // Set search parameters
        params.set('live', 'true');
        params.set('searchType', 'inPerson');
        params.set('lat', mapState.bounds.center[0]);
        params.set('lng', mapState.bounds.center[1]);

        // Update URL and fetch results
        updateUrlParams(params);
        fetchResults(params.toString());
    });
};

// Lifecycle hooks
onMounted(() => {
    // Initialize the SearchStore from URL and props
    SearchStore.initializeFromUrl(props.searchedEvents, props.maxPrice);
    
    // Subscribe to MapStore
    subscribeToMapStore();
    
    // Add event listener
    window.addEventListener('hide-search', hideSearch);
});

// Cleanup on component unmount
onUnmounted(() => {
    if (unsubscribe.value) {
        unsubscribe.value();
    }
    window.removeEventListener('hide-search', hideSearch);
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
