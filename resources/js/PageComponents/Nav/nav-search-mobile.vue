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
                            'text-gray-500 relative border-none p-4 text-4xl rounded-full transition-all duration-200',
                            search === 'l' ? 'font-bold' : 'font-normal',
                            'hover:bg-gray-100'
                        ]">
                        <span class="block font-bold invisible h-0">Location</span>
                        <span class="block" :class="{ 'font-bold text-black': search === 'l' }">Location</span>
                    </button>
                    <button 
                        @click="search='e'"
                        :class="[
                            'text-gray-500 relative border-none p-4 text-4xl rounded-full transition-all duration-200',
                            search === 'e' ? 'font-bold' : 'font-normal',
                            'hover:bg-gray-100'
                        ]">
                        <span class="block font-bold invisible h-0">Name</span>
                        <span class="block" :class="{ 'font-bold text-black': search === 'e' }">Name</span>
                    </button>
                    <button 
                        @click="openFilters"
                        class="absolute top-6 z-20 right-8 bg-white w-20 h-20 flex-shrink-0 flex items-center justify-center rounded-full shadow-custom-3 transition-colors"
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
                            style="display: block; fill: none; height: 18px; width: 18px; stroke-width: 2.5; overflow: visible;"
                            :style="{ stroke: hasActiveFilters ? 'white' : 'currentcolor' }"
                        >
                            <path fill="none" d="M7 16H3m26 0H15M29 6h-4m-8 0H3m26 20h-4M7 16a4 4 0 1 0 8 0 4 4 0 0 0-8 0zM17 6a4 4 0 1 0 8 0 4 4 0 0 0-8 0zm0 20a4 4 0 1 0 8 0 4 4 0 0 0-8 0zm0 0H3"></path>
                        </svg>
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
                                    :search="search"
                                    :show-dates-tab="showDatesTab"
                                    @update:location="handleLocationUpdate"
                                    @search="handleSearch"
                                />
                                <SearchEvent v-if="search==='e'" class="h-full"/>
                            </div>
                        </Transition>
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
                    <div class="w-full p-5 border rounded-full flex items-center shadow-custom-3">
                        <p class="w-full flex items-center justify-center text-center truncate">
                            <template v-if="state.location.city">
                                <span class="text-black text-1xl font-bold truncate max-w-[40%]" @click.stop="openLocationSearch">{{ state.location.city }}</span>
                                <span class="text-gray-300 mx-4">|</span>
                                <span class="text-black text-1xl truncate max-w-[40%]" :class="{ 'font-bold': state.dates.start }" @click.stop="openDateSearch">
                                    {{ state.dates.start ? formatDateDisplay : 'Add dates' }}
                                </span>
                            </template>
                            <template v-else-if="state.dates.start">
                                <span class="text-black text-1xl font-bold truncate" @click.stop="openLocationSearch">All Events</span>
                                <span class="text-gray-300 mx-4">|</span>
                                <span class="text-black text-1xl font-bold truncate" @click.stop="openDateSearch">
                                    {{ formatDateDisplay }}
                                </span>
                            </template>
                            <template v-else>
                                <div class="flex items-center justify-center w-full cursor-pointer" @click="openSearch">
                                    <svg class="w-6 h-6 fill-[#ff385c] mr-2">
                                        <use :xlink:href="`/storage/website-files/icons.svg#ri-search-line`" />
                                    </svg>
                                    <span class="text-black font-bold text-3xl">Search</span>
                                </div>
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
        ref="filtersComponent"
        v-model="state.filters"
        :show-price="isSearchPage"
        @close="closeFilters"
        @filter-change="handleFilterUpdate"
    />
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import SearchLocation from './Components/location-search-mobile.vue';
import SearchEvent from './Components/events-search-mobile.vue';
import Filters from './Components/filters-mobile.vue';
import SearchStore from '@/Stores/SearchStore.vue';
import MapStore from '@/Stores/MapStore.vue';


// Props definition
const props = defineProps({
    searchedEvents: {
        type: Object,
        default: () => ({})
    },
    maxPrice: {
        type: Number,
        default: undefined
    },
    showDatesTab: {
        type: Boolean,
        default: true
    }
});

// Replace the state ref with a computed property that uses SearchStore
const state = computed(() => SearchStore.state);

// UI state refs (just what the UI needs, not the search data)
const search = ref(null);
const showFilters = ref(false);
const unsubscribe = ref(null);
const searchLocation = ref(null);
// Add ref for filters component to access hasActiveFilters
const filtersComponent = ref(null);

// Add urlParams computed property to get current URL parameters
const urlParams = computed(() => {
    // Always create a fresh URLSearchParams to ensure we have the latest URL state
    return new URLSearchParams(window.location.search);
});

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

// Replace the existing hasActiveFilters computed with one that uses the filters component
const hasActiveFilters = computed(() => {
    // Check if we have access to the filters component's hasActiveFilters
    if (filtersComponent.value?.hasActiveFilters) {
        return filtersComponent.value.hasActiveFilters.value;
    }
    
    // Simple fallback when filters component isn't mounted
    return state.value.filters.categories?.length > 0 || 
           state.value.filters.tags?.length > 0 || 
           state.value.filters.atHome === true || 
           state.value.filters.searchingByPrice === true;
});

// Add isSearchPage computed property
const isSearchPage = computed(() => {
    return window.location.pathname.includes('/index/search');
});

// Methods
const openSearch = () => {
    search.value = 'l';
    // Prevent background scrolling
    document.body.classList.add('overflow-hidden');
};

const openLocationSearch = () => {
    search.value = 'l';
    document.body.classList.add('overflow-hidden');
};

const openDateSearch = () => {
    // First set the search value to open the search modal
    search.value = 'l';
    document.body.classList.add('overflow-hidden');
    
    // Use nextTick to ensure component is mounted before accessing its methods
    nextTick(() => {
        // Try an immediate call first
        if (searchLocation.value && searchLocation.value.showDatesSection) {
            searchLocation.value.showDatesSection();
        }
        
        // Also set a backup timeout to ensure it gets called even if the component takes time to initialize
        setTimeout(() => {
            if (searchLocation.value && searchLocation.value.showDatesSection) {
                searchLocation.value.showDatesSection();
            }
        }, 100);
    });
};

const hideSearch = () => {
    // We don't want to clear the location data that's already in the store when simply closing the modal
    // Only reset the component state and close the modal
    
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
                live: false
            },
            dates: {
                start: value.start,
                end: value.end
            }
        });
        
        // If we have location data, make sure atHome is false
        if (state.value.filters.atHome) {
            SearchStore.updateState({
                filters: {
                    ...state.value.filters,
                    atHome: false
                }
            });
        }
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
    
    // Check if remote toggle is enabled
    const isRemoteMode = state.value.filters.atHome === true;
    
    // Add location parameters if we have a city and NOT in remote mode
    if (state.value.location.city && !isRemoteMode) {
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
    } else {
        // For remote mode or date-only search
        params.set('searchType', isRemoteMode ? 'atHome' : 'null');
        
        // Remove any previous location data if it exists
        params.delete('city');
        params.delete('lat');
        params.delete('lng');
        params.delete('NElat');
        params.delete('NElng');
        params.delete('SWlat');
        params.delete('SWlng');
        params.delete('live');
    }
    
    // Add date parameters if they exist
    if (state.value.dates.start) {
        params.set('start', state.value.dates.start);
        params.set('end', state.value.dates.end || state.value.dates.start);
    } else if (!state.value.location.city && !isRemoteMode) {
        // If we have neither location nor dates, and not in remote mode, don't search
        hideSearch();
        return;
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
    if (maxPrice < state.value.filters.maxPrice) {
        params.set('price1', maxPrice);
    }
    
    // Hide search modal after search
    hideSearch();
    
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
        // Pass true to indicate this is a full clear
        searchLocation.value.clearState(true);
    }
    
    // Dispatch the clear-search-state event to ensure all components reset
    window.dispatchEvent(new CustomEvent('clear-search-state'));
};

const openFilters = () => {
    showFilters.value = true;
    // Prevent background scrolling when filters are open
    document.body.classList.add('overflow-hidden');
};

const closeFilters = () => {
    showFilters.value = false;
    document.body.classList.remove('overflow-hidden');
};

const handleFilterUpdate = (filters) => {
    // Update SearchStore with the new filters
    SearchStore.updateState({
        filters: filters
    });
    
    // Hide filters and restore scrolling
    showFilters.value = false;
    document.body.classList.remove('overflow-hidden');
    
    // Close search modal when filters are applied
    search.value = null;
    
    // If we're on search page, update URL and fetch results
    if (window.location.pathname.includes('/index/search')) {
        const params = getUrlParams();
        
        // Get current search type to detect mode changes
        const currentSearchType = params.get('searchType');
        const newSearchType = filters.atHome ? 'atHome' : 
                              (state.value.location.city ? 'inPerson' : 'null');
        
        // Detect if we're switching between in-person and remote modes
        const isCurrentlyInPerson = currentSearchType === 'inPerson';
        const isCurrentlyRemote = currentSearchType === 'atHome';
        const isTogglingModes = (isCurrentlyInPerson && filters.atHome) || 
                               (isCurrentlyRemote && !filters.atHome);
        
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
            // Update searchingByPrice if both price params are being removed
            if (!params.has('price1')) {
                SearchStore.updateState({
                    filters: {
                        ...filters,
                        searchingByPrice: false
                    }
                });
            }
        }
        if (maxPrice < state.value.filters.maxPrice) {
            params.set('price1', maxPrice);
        } else {
            params.delete('price1');
            // Update searchingByPrice if both price params are being removed
            if (!params.has('price0')) {
                SearchStore.updateState({
                    filters: {
                        ...filters,
                        searchingByPrice: false
                    }
                });
            }
        }

        // Set searchType parameter based on atHome filter
        params.set('searchType', newSearchType);
        
        // Remove location data when switching to atHome mode
        if (filters.atHome) {
            params.delete('city');
            params.delete('lat');
            params.delete('lng');
            params.delete('NElat');
            params.delete('NElng');
            params.delete('SWlat');
            params.delete('SWlng');
            params.delete('live');
        }
        
        // Do a full redirect when toggling between modes, otherwise update in place
        if (isTogglingModes) {
            window.location.href = `/index/search?${params.toString()}`;
        } else {
            updateUrlParams(params);
            fetchResults(params.toString());
        }
    } else {
        // If we're on the home page, redirect to search page with filters
        const params = new URLSearchParams();
        
        if (filters.categories.length) {
            params.set('category', filters.categories.join(','));
        }
        if (filters.tags.length) {
            params.set('tag', filters.tags.join(','));
        }
        
        // Handle price if needed
        const [minPrice, maxPrice] = filters.price;
        if (minPrice > 0) {
            params.set('price0', minPrice);
        }
        if (maxPrice < state.value.filters.maxPrice) {
            params.set('price1', maxPrice);
        }
        
        // Set searchType based on atHome filter
        params.set('searchType', filters.atHome ? 'atHome' : 'null');
        
        window.location.href = `/index/search?${params.toString()}`;
    }
};

const handleBack = () => {
    window.location.href = '/';
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
    SearchStore.initializeFromUrl(props.searchedEvents, props.maxPrice === undefined ? null : props.maxPrice);
    
    // Additional check to ensure date parameters are read correctly
    const params = new URLSearchParams(window.location.search);
    if (params.has('start') && !state.value.dates.start) {
        const start = params.get('start');
        const end = params.get('end') || start;
        
        // Update the store with dates, even if no location is present
        SearchStore.updateState({
            dates: {
                start: start,
                end: end
            }
        });
    }
    
    // Subscribe to MapStore
    subscribeToMapStore();
    
    // Add event listeners
    window.addEventListener('hide-search', hideSearch);
    
    // Add listener for opening filters from anywhere in the app
    window.addEventListener('open-filters', openFilters);
});

// Cleanup on component unmount
onUnmounted(() => {
    if (unsubscribe.value) {
        unsubscribe.value();
    }
    window.removeEventListener('hide-search', hideSearch);
    window.removeEventListener('open-filters', openFilters);
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
