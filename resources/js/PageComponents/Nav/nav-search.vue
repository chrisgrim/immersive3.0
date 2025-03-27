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
                </div>
                <div class="w-full mx-auto flex relative max-w-6xl mt-8">
                    <SearchLocation 
                        v-if="search==='l'"
                        ref="locationSearch"
                        @search="handleLocationSearch"
                        @close-search="search = null"
                        :initial-start-date="startDate" 
                        :initial-end-date="endDate"
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
                            <template v-if="city">
                                <div 
                                    class="flex items-center cursor-pointer" 
                                    @click="openLocationSearch">
                                    <svg class="w-6 h-6 fill-[#ff385c] mr-2">
                                        <use :xlink:href="`/storage/website-files/icons.svg#ri-search-line`" />
                                    </svg>
                                    <span class="text-black text-1xl font-bold mr-10">{{ city }}</span>
                                </div>
                                <span class="text-gray-300">|</span>
                                <div 
                                    class="ml-10 cursor-pointer" 
                                    @click="openDateSearch">
                                    <span class="text-black text-1xl" :class="{ 'font-bold': startDate }">
                                        {{ startDate ? formatDateDisplay : 'Add dates' }}
                                    </span>
                                </div>
                            </template>
                            <template v-else>
                                <div 
                                    class="flex items-center cursor-pointer w-full" 
                                    @click="openLocationSearch">
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
        <Filters
            v-if="showFilters"
            v-model="state.filters"
            :max-price="maxPrice"
            :show-price="isSearchPage"
            @close="showFilters = false"
            @filter-change="handleFilterUpdate"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import SearchLocation from './Components/location-search.vue';
import SearchEvent from './Components/events-search.vue';
import Filters from './Components/filters.vue';
import axios from 'axios';
import SearchStore from '@/Stores/SearchStore.vue';
import MapStore from '@/Stores/MapStore.vue';

// Add props definition at the top of script setup
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

// Refs (replaced data properties)
const search = ref(null);
const city = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const lat = ref(null);
const lng = ref(null);
const showFilters = ref(false);
const selectedCategories = ref([]);
const selectedTags = ref([]);
const priceRange = ref([0, 1000]);
const unsubscribe = ref(null);

// For template refs
const locationSearch = ref(null);


// State management
const state = ref({
    events: {
        data: props.searchedEvents?.data || [],
        total: props.searchedEvents?.total || 0,
        current_page: props.searchedEvents?.current_page || 1,
        last_page: props.searchedEvents?.last_page || 1,
        from: props.searchedEvents?.from || null,
        to: props.searchedEvents?.to || null,
        per_page: props.searchedEvents?.per_page || 15
    },
    location: {
        city: null,
        lat: null,
        lng: null,
        searchType: null,
        live: false
    },
    filters: {
        categories: [],
        tags: [],
        price: [0, props.maxPrice],
        searchedMaxPrice: props.maxPrice
    },
    maxPrice: props.maxPrice
});

// Computed properties
const isSearchPage = computed(() => {
    return window.location.pathname.includes('/search');
});

const formatDateDisplay = computed(() => {
    if (!startDate.value) return '';

    const start = new Date(startDate.value);
    const end = new Date(endDate.value);
    
    // Format dates
    const formatDate = (date) => {
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric' 
        });
    };

    // If dates are the same, show only one date
    if (start.getTime() === end.getTime()) {
        return formatDate(start);
    } else {
        return `${formatDate(start)} - ${formatDate(end)}`;
    }
});

const hasActiveFilters = computed(() => {
    const result = (state.value.filters.categories?.length > 0) || 
           (state.value.filters.tags?.length > 0) || 
           (isSearchPage.value && (
               state.value.filters.price?.[0] !== 0 || 
               state.value.filters.price?.[1] !== state.value.maxPrice
           ));

    return result;
});

const openLocationSearch = () => {
    search.value = 'l';
};

const openDateSearch = () => {
    // First open the search modal in location mode
    search.value = 'l';
    
    // Use nextTick to ensure the component is mounted
    nextTick(async () => {
        // Add a small delay to ensure component is fully mounted
        await new Promise(resolve => setTimeout(resolve, 100));
        
        // Then open the date dropdown using the exposed method
        if (locationSearch.value) {
            locationSearch.value.openDateDropdown();
        }
    });
};

const handleScroll = () => {
    search.value = null;
};

const checkClickPosition = (event) => {
    if (event.clientY > 150) {
        search.value = null;
    }
};

const hideSearch = () => {
    search.value = null;
};

// Update handleFilterUpdate to use state.filters
const handleFilterUpdate = (filters) => {
    console.log('Filters updated:', filters);
    
    // Update state.filters
    state.value.filters = filters;
    
    if (isSearchPage.value) {
        // Update URL and state
        const params = new URLSearchParams(window.location.search);
        
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

        // Handle price parameters
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

        // Update URL without reload
        window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`);
        
        // Fetch new results
        fetchResults(params.toString());
    } else {
        // Redirect to search page with filters
        const params = new URLSearchParams();
        
        if (filters.categories.length) {
            params.set('category', filters.categories.join(','));
        }
        if (filters.tags.length) {
            params.set('tag', filters.tags.join(','));
        }
        
        // Handle price parameters for redirect
        const [minPrice, maxPrice] = filters.price;
        if (minPrice > 0) {
            params.set('price0', minPrice);
        }
        if (maxPrice < state.value.maxPrice) {
            params.set('price1', maxPrice);
        }
        
        params.set('searchType', 'null');

        window.location.href = `/index/search?${params.toString()}`;
    }
};

const openFilters = () => {
    showFilters.value = true;
};

const handleLocationSearch = (searchData) => {    
    city.value = searchData.location.city;
    startDate.value = searchData.dates.start;
    endDate.value = searchData.dates.end;
    lat.value = searchData.location.lat;
    lng.value = searchData.location.lng;

    // Update store state
    state.value = {
        ...state.value,
        location: {
            ...searchData.location,
            live: searchData.location.live
        },
        dates: searchData.dates
    };

    // Get current city from URL before modifying params
    const currentUrlParams = new URLSearchParams(window.location.search);
    const currentCity = currentUrlParams.get('city');
    const isNewLocation = currentCity !== searchData.location.city;

    const params = new URLSearchParams(window.location.search);
    
    // Only reset these parameters if location changes
    if (isNewLocation) {
        // Remove any existing boundary parameters with correct names
        params.delete('NElat');
        params.delete('NElng');
        params.delete('SWlat');
        params.delete('SWlng');
        params.set('searchType', 'inPerson');
        params.set('live', 'false');
    }
    
    // Set new parameters
    params.set('city', searchData.location.city);
    params.set('lat', searchData.location.lat);
    params.set('lng', searchData.location.lng);
    
    // Add price parameters from current state
    const [minPrice, maxPrice] = state.value.filters.price;
    if (minPrice > 0) {
        params.set('price0', minPrice);
    }
    if (maxPrice < state.value.maxPrice) {
        params.set('price1', maxPrice);
    }
    
    if (searchData.dates.start) {
        params.set('start', searchData.dates.start);
        params.set('end', searchData.dates.end || searchData.dates.start);
    } else {
        params.delete('start');
        params.delete('end');
    }

    if (isNewLocation) {
        // If this is a new location search, redirect
        window.location.href = `/index/search?${params.toString()}`;
    } else {
        // If this is just a date update on the search page, update URL and fetch
        window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`);
        fetchResults(params.toString());
    }
};

// Initialize from URL
const initializeFromUrl = () => {
    const params = new URLSearchParams(window.location.search);
    
    // Update date refs from URL
    if (params.has('start') && params.has('end')) {
        startDate.value = params.get('start');
        endDate.value = params.get('end');
    }
    
    state.value = {
        events: {
            data: props.searchedEvents?.data || [],
            total: props.searchedEvents?.total || 0,
            current_page: props.searchedEvents?.current_page || 1,
            last_page: props.searchedEvents?.last_page || 1,
            from: props.searchedEvents?.from || null,
            to: props.searchedEvents?.to || null,
            per_page: props.searchedEvents?.per_page || 15
        },
        location: {
            city: params.get('city') || null,
            lat: params.has('lat') ? parseFloat(params.get('lat')) : null,
            lng: params.has('lng') ? parseFloat(params.get('lng')) : null,
            searchType: params.get('searchType') || null,
            live: params.get('live') === 'true'
        },
        filters: {
            categories: params.has('category') ? params.get('category').split(',').map(Number) : [],
            tags: params.has('tag') ? params.get('tag').split(',').map(Number) : [],
            price: [
                parseInt(params.get('price0')) || 0,
                parseInt(params.get('price1')) || props.maxPrice
            ],
            searchedMaxPrice: props.maxPrice
        },
        maxPrice: props.maxPrice
    };

    // Update local refs
    city.value = state.value.location.city;
    lat.value = state.value.location.lat;
    lng.value = state.value.location.lng;
    selectedCategories.value = state.value.filters.categories;
    selectedTags.value = state.value.filters.tags;
    priceRange.value = state.value.filters.price;
};

// Fetch results
const fetchResults = async (queryString) => {
    console.log('Fetching results with query:', queryString);
    try {
        SearchStore.setLoading(true);
        const response = await axios.get(`/api/index/search?${queryString}`);
        
        // Construct complete state object
        const completeState = {
            events: {
                current_page: response.data.current_page,
                data: response.data.data,
                from: response.data.from,
                last_page: response.data.last_page,
                per_page: response.data.per_page,
                to: response.data.to,
                total: response.data.total
            },
            location: {
                city: response.data.city,
                lat: response.data.lat,
                lng: response.data.lng,
                searchType: response.data.searchType,
                live: response.data.live
            },
            maxPrice: response.data.maxPrice
        };
        console.log('Complete state:', completeState);

        SearchStore.updateState(completeState);
    } catch (error) {
        console.error('Error fetching results:', error);
    } finally {
        SearchStore.setLoading(false);
    }
};

// Lifecycle hooks
onMounted(() => {
    initializeFromUrl();
    
    // Initialize SearchStore with complete state if searchedEvents available
    if (props.searchedEvents && Object.keys(props.searchedEvents).length > 0) {
        SearchStore.updateState(state.value);
    }
    
    // Update the MapStore subscription
    const unsubscribeMap = MapStore.subscribe((mapState) => {
        // Get current params to preserve other values
        const params = new URLSearchParams(window.location.search);
        
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
        window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`);
        
        // Fetch new results with the updated parameters
        fetchResults(params.toString());
    
    });
    
    window.addEventListener('scroll', handleScroll);
    window.addEventListener('hide-search', hideSearch);

    // Store unsubscribe function
    unsubscribe.value = unsubscribeMap;
});

// Separate onUnmounted hook
onUnmounted(() => {
    if (unsubscribe.value) {
        unsubscribe.value();
    }
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('hide-search', hideSearch);
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