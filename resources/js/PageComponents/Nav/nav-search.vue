<template>
    <div class="w-full inline-block relative min-w-[30rem] col-span-1 z-20">
        <div>
        </div>
        <template v-if="search">
            <div 
                @click="checkClickPosition"
                class="search-container fixed pt-8 left-0 top-0 w-full z-20">
                <div class="w-full flex justify-center items-center gap-2">
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
                    <button 
                        @click="showFilters = true"
                        class="w-[4.5rem] h-[4.5rem] ml-6 flex-shrink-0 flex items-center justify-center rounded-full shadow-custom-3 transition-colors"
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
                <div class="w-full mx-auto flex relative max-w-6xl mt-8">
                    <SearchLocation 
                        v-if="search==='l'"
                        ref="locationSearch"
                        @search="handleLocationSearch"
                        @close-search="search = null"
                        @dates-cleared="handleDatesClear"
                        :initial-start-date="state?.dates?.start" 
                        :initial-end-date="state?.dates?.end"
                    />
                    <SearchEvent v-if="search==='e'"/>
                </div>
                <div class="fixed top-0 left-0 w-full h-full bg-[#00000026] z-[-10]" />
            </div>
        </template>
        <template v-else>
            <div class="flex items-center justify-center gap-8">
                <div class="relative w-[39rem]">
                    <div class="p-4 border rounded-full flex items-center shadow-custom-3 w-[39rem]">
                        <p class="ml-4 flex items-center justify-center gap-2 flex-1">
                            <template v-if="state.location.city">
                                <div 
                                    class="flex items-center cursor-pointer" 
                                    @click="search = 'l'">
                                    <svg class="w-6 h-6 fill-[#ff385c] mr-2">
                                        <use :xlink:href="`/storage/website-files/icons.svg#ri-search-line`" />
                                    </svg>
                                    <span class="text-black text-1xl font-bold mr-10">{{ formattedCity }}</span>
                                </div>
                                <span class="text-gray-300">|</span>
                                <div 
                                    class="ml-10 cursor-pointer" 
                                    @click="openDateSearch">
                                    <span class="text-black text-1xl" :class="{ 'font-bold': state.dates.start }">
                                        {{ state.dates.start ? formatDateDisplay : 'Add dates' }}
                                    </span>
                                </div>
                            </template>
                            <template v-else-if="state.dates.start">
                                <div 
                                    class="flex items-center cursor-pointer" 
                                    @click="search = 'l'">
                                    <svg class="w-6 h-6 fill-[#ff385c] mr-2">
                                        <use :xlink:href="`/storage/website-files/icons.svg#ri-search-line`" />
                                    </svg>
                                    <span class="text-black text-1xl font-bold mr-10">All Events</span>
                                </div>
                                <span class="text-gray-300">|</span>
                                <div 
                                    class="ml-10 cursor-pointer" 
                                    @click="openDateSearch">
                                    <span class="text-black text-1xl font-bold">
                                        {{ formatDateDisplay }}
                                    </span>
                                </div>
                            </template>
                            <template v-else>
                                <div 
                                    class="flex items-center cursor-pointer" 
                                    @click="search = 'l'">
                                    <svg class="w-6 h-6 fill-[#ff385c] mr-2">
                                        <use :xlink:href="`/storage/website-files/icons.svg#ri-search-line`" />
                                    </svg>
                                    <span class="text-black text-1xl">Start your search</span>
                                </div>
                            </template>
                        </p>
                    </div>
                </div>
                <button 
                    @click="showFilters = true"
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
        <Filters
            v-if="showFilters"
            v-model="state.filters"
            :show-price="isSearchPage"
            @close="showFilters = false"
            @filter-change="handleFilterUpdate"
            ref="filtersComponent"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import SearchLocation from './Components/location-search.vue';
import SearchEvent from './Components/events-search.vue';
import Filters from './Components/filters.vue';
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
  }
});

// Replace the state computed property with this simpler version
const state = computed(() => SearchStore.state);

// UI state refs (just what the UI needs, not the search data)
const search = ref(null);
const showFilters = ref(false);
const unsubscribe = ref(null);
const locationSearch = ref(null);
// Add ref for filters component to access hasActiveFilters
const filtersComponent = ref(null);

// Remove the getUrlParams utility function and replace with computed property
const urlParams = computed(() => new URLSearchParams(window.location.search));

const updateUrlParams = (params) => {
    window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`);
};

// Computed properties
const isSearchPage = computed(() => {
    return window.location.pathname.includes('/search');
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

// Update hasActiveFilters to use the filters component
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

const debounce = (fn, delay) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
};

const openDateSearch = () => {
    search.value = 'l';
    nextTick(async () => {
        await new Promise(resolve => setTimeout(resolve, 100));
        if (locationSearch.value) {
            locationSearch.value.openDateDropdown();
        }
    });
};

const handleScroll = debounce(() => {
    search.value = null;
}, 150);

const checkClickPosition = (event) => {
    if (event.clientY > 150) {
        search.value = null;
    }
};

const hideSearch = () => {
    search.value = null;
};

const handleFilterUpdate = async (filters) => {
    SearchStore.updateState({
        filters: filters
    });
    
    // Close the filters modal
    showFilters.value = false;
    
    // Close the search modal when filters are applied
    search.value = null;
    
    if (isSearchPage.value) {
        const params = urlParams.value;
        
        // Get current search type to detect mode changes
        const currentSearchType = params.get('searchType');
        const newSearchType = filters.atHome ? 'atHome' : 'null';
        
        // Detect if we're switching between in-person and remote modes
        const isCurrentlyInPerson = currentSearchType === 'inPerson';
        const isCurrentlyRemote = currentSearchType === 'atHome';
        const isTogglingModes = (isCurrentlyInPerson && filters.atHome) || 
                               (isCurrentlyRemote && !filters.atHome);
        
        params.set('page', 1);
        
        // Handle categories
        if (filters.categories.length) {
            params.set('category', filters.categories.join(','));
        } else {
            params.delete('category');
        }
        
        // Handle tags
        if (filters.tags.length) {
            params.set('tag', filters.tags.join(','));
        } else {
            params.delete('tag');
        }

        // Handle price range
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
            console.log('removing price');
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

        // Set searchType based on atHome filter
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
            await fetchResults(params.toString());
        }
    } else {
        const params = new URLSearchParams();
        
        if (filters.categories.length) {
            params.set('category', filters.categories.join(','));
        }
        if (filters.tags.length) {
            params.set('tag', filters.tags.join(','));
        }
        
        const [minPrice, maxPrice] = filters.price;
        if (minPrice > 0) {
            params.set('price0', minPrice);
        }
        if (maxPrice < state.value.filters.maxPrice) {
            params.set('price1', maxPrice);
        }

        // Handle searchType based on atHome
        params.set('searchType', filters.atHome ? 'atHome' : 'null');
        
        window.location.href = `/index/search?${params.toString()}`;
    }
};

const handleLocationSearch = (searchData) => {    
    console.log('handleLocationSearch', searchData);
    
    // Use the updateState method to update the store with search data
    SearchStore.updateState({
        location: {
            city: searchData.location.city,
            lat: searchData.location.lat,
            lng: searchData.location.lng,
            live: searchData.location.live
        },
        dates: {
            start: searchData.dates.start,
            end: searchData.dates.end
        }
    });
    
    // Set up URL parameters
    const params = urlParams.value;
    const currentCity = params.get('city'); // Get current city here so it's in scope
    
    // Reset to page 1 for any new search
    params.set('page', 1);
    
    // Handle date parameters
    if (searchData.dates.start) {
        params.set('start', searchData.dates.start);
        params.set('end', searchData.dates.end || searchData.dates.start);
    } else {
        params.delete('start');
        params.delete('end');
    }
    
    // Check if we have location data
    const hasLocationData = searchData.location.city !== null;
    let isNewLocation = false;
    
    if (hasLocationData) {
        isNewLocation = currentCity !== searchData.location.city;
        
        // Clear map bounds if location has changed
        if (isNewLocation) {
            params.delete('NElat');
            params.delete('NElng');
            params.delete('SWlat');
            params.delete('SWlng');
            params.set('searchType', 'inPerson');
            params.set('live', 'false');
        }
        
        // Set location parameters
        params.set('city', searchData.location.city);
        
        // Explicitly convert to float before setting params
        const parsedLat = searchData.location.lat ? parseFloat(searchData.location.lat) : null;
        const parsedLng = searchData.location.lng ? parseFloat(searchData.location.lng) : null;
        
        if (parsedLat !== null) {
            params.set('lat', parsedLat.toString());
        }
        if (parsedLng !== null) {
            params.set('lng', parsedLng.toString());
        }
        
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
        // For date-only search, use 'null' searchType
        params.set('searchType', 'null');
        
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
    
    // Add price filter params if needed
    const [minPrice, maxPrice] = state.value.filters.price;
    if (minPrice > 0) {
        params.set('price0', minPrice);
    }
    if (maxPrice < state.value.maxPrice) {
        params.set('price1', maxPrice);
    }

    // Determine whether to redirect or update URL based on conditions
    if (hasLocationData && isNewLocation) {
        window.location.href = `/index/search?${params.toString()}`;
    } else {
        updateUrlParams(params);
        fetchResults(params.toString());
    }
};

// Fetch results
const fetchResults = async (queryString) => {
    try {
        await SearchStore.fetchResults(queryString);
    } catch (error) {
        console.error('Error in fetchResults:', error);
    }
};

// Set up subscription to MapStore for map-based searches
const subscribeToMapStore = () => {
  const unsubscribeMap = MapStore.subscribe((mapState) => {
    // Get current params to preserve other values
    const params = urlParams.value;
    
    // Update boundary coordinates
    params.set('NElat', parseFloat(mapState.bounds.northEast.lat).toFixed(6));
    params.set('NElng', parseFloat(mapState.bounds.northEast.lng).toFixed(6));
    params.set('SWlat', parseFloat(mapState.bounds.southWest.lat).toFixed(6));
    params.set('SWlng', parseFloat(mapState.bounds.southWest.lng).toFixed(6));
    params.set('live', 'true');
    params.set('searchType', 'inPerson');

    // Update center coordinates
    params.set('lat', mapState.bounds.center[0]);
    params.set('lng', mapState.bounds.center[1]);

    // Update URL without reload
    updateUrlParams(params);
    
    // Fetch new results with the updated parameters
    fetchResults(params.toString());
  });
  
  // Store unsubscribe function for cleanup
  unsubscribe.value = unsubscribeMap;
};

// Setup event listeners for UI interactions
const setupEventListeners = () => {
  window.addEventListener('scroll', handleScroll);
  window.addEventListener('hide-search', hideSearch);
};

// Lifecycle hooks
onMounted(() => {
  SearchStore.initializeFromUrl(props.searchedEvents, props.maxPrice === undefined ? null : props.maxPrice);
  subscribeToMapStore();
  setupEventListeners();
  
  // Add listener for opening filters from anywhere in the app
  window.addEventListener('open-filters', () => showFilters.value = true);
});

// Cleanup on component unmount
onUnmounted(() => {
  // Only need to clean up MapStore subscription
  if (unsubscribe.value) {
    unsubscribe.value();
  }
  
  // Remove event listeners
  window.removeEventListener('scroll', handleScroll);
  window.removeEventListener('hide-search', hideSearch);
  window.removeEventListener('open-filters', () => showFilters.value = true);
});

// Add after handleLocationSearch
const handleDatesClear = () => {
    // Update the store with null dates
    SearchStore.updateState({
        dates: {
            start: null,
            end: null
        }
    });
    
    // Update URL parameters
    const params = urlParams.value;
    params.delete('start');
    params.delete('end');
    
    // If we're on a search page, update the search results
    if (isSearchPage.value) {
        updateUrlParams(params);
        fetchResults(params.toString());
    }
    
    // Close the search popup after clearing dates
    search.value = null;
};

// Add this computed property after other computed properties
const formattedCity = computed(() => {
    if (!state.value.location.city) return '';
    
    // Extract just the city name from "City, State Zipcode" format
    const cityParts = state.value.location.city.split(',');
    return cityParts[0].trim();
});
</script>

<style scoped>
.search-container::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 18rem;
    background: white;
    z-index: -1;
    box-shadow: 0 1px 12px rgba(0, 0, 0, 0.08);
}
</style>