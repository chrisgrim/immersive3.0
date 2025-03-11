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
                        @location-updated="updateLocationData"
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
            :selected-categories="selectedCategories"
            :selected-tags="selectedTags"
            :price-range="priceRange"
            :max-price="1000"
            :show-price="isSearchPage"
            @close="showFilters = false"
            @update:filters="handleFilterUpdate"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import SearchLocation from './Components/location-search.vue';
import SearchEvent from './Components/events-search.vue';
import Filters from './Components/filters.vue';
import eventStore from '@/Stores/EventStore';

// Initialize URL params
const urlParams = new URLSearchParams(window.location.search);

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
const categories = ref([]);
const tags = ref([]);
const unsubscribe = ref(null);

// For template refs
const locationSearch = ref(null);

// Computed properties
const isSearchPage = computed(() => {
    return window.location.pathname.includes('/search');
});

const searchPlaceholder = computed(() => {
    if (!city.value) return 'Start your search';

    let text = city.value;

    if (startDate.value && endDate.value) {
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
            text += ` · ${formatDate(start)}`;
        } else {
            text += ` · ${formatDate(start)} - ${formatDate(end)}`;
        }
    }

    return text;
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
    return (selectedCategories.value?.length > 0) || 
           (selectedTags.value?.length > 0) || 
           (priceRange.value?.[0] !== 0) || 
           (priceRange.value?.[1] !== 1000);
});

// Methods
const openSearch = () => {
    search.value = 'l';
};

const openLocationSearch = () => {
    search.value = 'l';
};

const openDateSearch = () => {
    // Open search in location mode first
    search.value = 'l';
    
    // Use a small timeout to ensure the component is mounted
    setTimeout(() => {
        if (locationSearch.value) {
            locationSearch.value.openDateDropdown();
        }
    }, 50);
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

const handleFilterUpdate = ({ categories: cats, tags: t, price }) => {
    // Update local UI state
    selectedCategories.value = cats;
    selectedTags.value = t;
    priceRange.value = price;
    
    // Update store
    eventStore.update({
        filters: {
            categories: cats,
            tags: t,
            price: price
        }
    });
};

const openFilters = () => {
    showFilters.value = true;
};

const updateLocationData = (data) => {
    // Update local UI state for the nav
    city.value = data.city;
    startDate.value = data.startDate;
    endDate.value = data.endDate;
    lat.value = data.lat;
    lng.value = data.lng;
};

// Lifecycle hooks
onMounted(() => {
    window.addEventListener('scroll', handleScroll);
    window.addEventListener('hide-search', hideSearch);
    
    // Subscribe to EventStore changes
    unsubscribe.value = eventStore.subscribe(state => {
        // Update local state from the store
        city.value = state.location.city;
        lat.value = state.location.lat;
        lng.value = state.location.lng;
        startDate.value = state.dates.start;
        endDate.value = state.dates.end;
        selectedCategories.value = state.filters.categories;
        selectedTags.value = state.filters.tags;
        priceRange.value = state.filters.price;
    });
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('hide-search', hideSearch);
    
    if (unsubscribe.value) {
        unsubscribe.value();
    }
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