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
                                    @click="openLocationSearch">
                                    <svg class="w-6 h-6 fill-[#ff385c] mr-2">
                                        <use :xlink:href="`/storage/website-files/icons.svg#ri-search-line`" />
                                    </svg>
                                    <span class="text-black text-1xl font-bold mr-10">{{ state.location.city }}</span>
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

// Props definition
const props = defineProps({
  searchedEvents: {
    type: Object,
    default: () => ({
      data: [],
      total: 0,
      current_page: 1,
      last_page: 1,
      from: null,
      to: null,
      per_page: 15
    })
  },
  maxPrice: {
    type: Number,
    default: 1000
  }
});

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
    dates: {
        start: null,
        end: null
    },
    filters: {
        categories: [],
        tags: [],
        price: [0, props.maxPrice],
        searchedMaxPrice: props.maxPrice
    },
    maxPrice: props.maxPrice
});

// UI state refs
const search = ref(null);
const showFilters = ref(false);
const unsubscribe = ref(null);

// For template refs
const locationSearch = ref(null);

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

const hasActiveFilters = computed(() => {
    return (state.value.filters.categories?.length > 0) || 
           (state.value.filters.tags?.length > 0) || 
           (isSearchPage.value && (
               state.value.filters.price?.[0] !== 0 || 
               state.value.filters.price?.[1] !== state.value.maxPrice
           ));
});

// Methods
const openLocationSearch = () => {
    search.value = 'l';
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

const handleFilterUpdate = (filters) => {
    state.value.filters = filters;
    
    if (isSearchPage.value) {
        const params = getUrlParams();
        
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
    state.value = {
        ...state.value,
        location: {
            ...searchData.location,
            live: searchData.location.live
        },
        dates: searchData.dates
    };

    const params = getUrlParams();
    const currentCity = params.get('city');
    const isNewLocation = currentCity !== searchData.location.city;
    
    if (isNewLocation) {
        params.delete('NElat');
        params.delete('NElng');
        params.delete('SWlat');
        params.delete('SWlng');
        params.set('searchType', 'inPerson');
        params.set('live', 'false');
    }
    
    params.set('city', searchData.location.city);
    params.set('lat', searchData.location.lat);
    params.set('lng', searchData.location.lng);
    
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
        window.location.href = `/index/search?${params.toString()}`;
    } else {
        updateUrlParams(params);
        fetchResults(params.toString());
    }
};

// Initialize from URL
const initializeFromUrl = () => {
    const params = getUrlParams();
    console.log('URL Params:', Object.fromEntries(params));
    
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
        dates: {
            start: params.get('start') || null,
            end: params.get('end') || null
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
    
    console.log('Initialized state:', state.value);
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
        SearchStore.updateState(completeState);
    } catch (error) {
        console.error('Error fetching results:', error);
    } finally {
        SearchStore.setLoading(false);
    }
};

// Initialize SearchStore with current state
const initializeSearchStore = () => {
  if (props.searchedEvents && Object.keys(props.searchedEvents).length > 0) {
    SearchStore.updateState(state.value);
  }
};

// Set up subscription to MapStore for map-based searches
const subscribeToMapStore = () => {
  const unsubscribeMap = MapStore.subscribe((mapState) => {
    // Get current params to preserve other values
    const params = getUrlParams();
    
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
  initializeFromUrl();
  initializeSearchStore();
  subscribeToMapStore();
  setupEventListeners();
});

// Cleanup on component unmount
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