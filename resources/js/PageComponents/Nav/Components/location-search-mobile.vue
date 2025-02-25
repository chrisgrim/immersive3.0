<template>
    <div class="w-full h-full flex flex-col gap-8 pb-8">
        <!-- Add this hidden div for Google Places service -->
        <div id="places" style="display: none;"></div>
        
        <!-- Location Section -->
        <div 
            @click="showLocationSection"
            v-if="isVisible==='dates'" 
            class="relative flex w-full border rounded-4xl bg-white shadow-custom-3 p-8 justify-between">
            <div>
                <p>Where</p>
            </div>
            <div>
                <p>{{ searchInput || 'New York' }}</p>
            </div>
        </div>
        <div v-else class="flex flex-col relative w-full border rounded-4xl bg-white shadow-custom-6 p-12">
            <div class="w-full">
                <h2 class="text-4xl leading-8 font-bold">Where To?</h2>
            </div>
            <div class="w-full flex-grow flex flex-col mt-10">
                <div class="w-full border border-slate-400 rounded-2xl flex items-center">
                    <svg class="w-8 h-8 fill-black z-[1002] ml-8">
                        <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                    </svg>
                    <input 
                        ref="loc"
                        class="relative text-1xl p-8 w-full font-bold z-40 bg-transparent focus:border-none placeholder-slate-400"
                        v-model="searchInput"
                        placeholder="Search by City"
                        @input="updateLocations"
                        @focus="dropdown=true"
                        autocomplete="false"
                        onfocus="value = ''" 
                        type="text">
                </div>
                
                <!-- Location Dropdown -->
                <ul 
                    class="bg-white w-full mx-0 overflow-hidden py-8 list-none" 
                    v-if="dropdown"
                    @click.stop>
                    <li 
                        class="py-4 px-8 flex items-center gap-8 hover:bg-neutral-100" 
                        v-for="place in places"
                        :key="place.place_id"
                        @click.stop="selectLocation(place)">
                        <svg class="w-8 h-8 fill-black">
                            <use xlink:href="/storage/website-files/icons.svg#ri-map-pin-line"></use>
                        </svg>
                        {{place.description}}
                    </li>
                </ul>
            </div>
        </div>

        <!-- Dates Section -->
        <div 
            @click="showDatesSection"
            v-if="isVisible==='location'" 
            class="relative flex w-full border shadow-custom-3 rounded-4xl bg-white p-8 justify-between">
            <div>
                <p>When</p>
            </div>
            <div>
                <p>{{ date ? formatDateRange : 'Add Dates' }}</p>
            </div>
        </div>
        <div 
            v-else
            class="flex-grow relative w-full border shadow-custom-6 rounded-4xl bg-white p-8 overflow-auto h-full">
            <VueDatePicker
                v-model="date"
                range
                disable-year-select
                :multi-calendars="displayedMonths"
                :enable-time-picker="false"
                :dark="isDark"
                :timezone="tz"
                :preview-date="new Date()"
                inline
                auto-apply
                @update:model-value="handleDateChange"
                month-name-format="long"
                hide-offset-dates
                :month-change-on-scroll="false"
                week-start="0"
            />
            <div v-if="displayedMonths === 3" class="w-full flex justify-center mt-8 border-t pt-8">
                <button 
                    @click="loadMoreMonths"
                    class="text-black underline font-semibold hover:text-gray-600"
                >
                    Show more dates
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import axios from 'axios';

const props = defineProps({
    initialCity: String,
    initialStartDate: String,
    initialEndDate: String
});

// Existing date-related refs
const dateDropdown = ref(false);
const dropdown = ref(false);
const searchInput = ref('');
const places = ref(initializePlaces());
const date = ref(null);
const isVisible = ref('location');

// Location search functionality
let autoComplete;
let service;

// Add these new refs to store selected location data
const selectedPlace = ref(null);

// Add this ref for controlling displayed months
const displayedMonths = ref(3);

// Add emits definition
const emit = defineEmits(['update:location', 'clear']);

function initializePlaces() {
    return [
        { place_id: 'ChIJOwg_06VPwokRYv534QaPC8g', description: 'New York, NY, USA' },
        { place_id: 'ChIJE9on3F3HwoAR9AhGJW_fL-I', description: 'Los Angeles, CA, USA' },
        { place_id: 'ChIJIQBpAG2ahYAR_6128GcTUEo', description: 'San Francisco, CA, USA' },
        { place_id: 'ChIJ7cv00DwsDogRAMDACa2m4K8', description: 'Chicago, IL, USA' },
        { place_id: 'ChIJW-T2Wt7Gt4kRKl2I1CJFUsI', description: 'Washington, DC, USA' }
    ];
}

const updateLocations = () => {
    if (searchInput.value) {
        autoComplete.getPlacePredictions({ input: searchInput.value, types: ['(cities)'] }, data => {
            places.value = data || initializePlaces();
        });
    } else {
        places.value = initializePlaces();
    }
};

const selectLocation = (location) => {
   service.getDetails({ placeId: location.place_id }, data => {
       setPlace(data);
   });
};

const setPlace = (place) => {
   selectedPlace.value = {
       name: place.name,
       lat: place.geometry.location.lat(),
       lng: place.geometry.location.lng()
   };
   searchInput.value = place.name;
   dropdown.value = false;
   
   // Just emit the city name as a string, not an object
   emit('update:location', place.name);
   
   isVisible.value = 'dates';
};

const saveSearchData = (place) => {
   axios.post('/search/storedata', { type: 'location', name: place.name });
};

const initGoogleMaps = () => {
    try {
        autoComplete = new google.maps.places.AutocompleteService();
        service = new google.maps.places.PlacesService(document.getElementById("places"));
        // Show initial places immediately after initialization
        places.value = initializePlaces();
        dropdown.value = true;
    } catch (error) {
        console.error('Error initializing Google Maps:', error);
        // Fallback to initial places if Google Maps fails
        places.value = initializePlaces();
        dropdown.value = true;
    }
};

// Initialize from URL parameters
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    
    // Set initial city if provided
    if (props.initialCity) {
        searchInput.value = props.initialCity;
    }

    // Set initial dates if provided
    if (props.initialStartDate && props.initialEndDate) {
        date.value = [new Date(props.initialStartDate), new Date(props.initialEndDate)];
    }
    
    // Initialize location if present
    if (params.has('city') && params.has('lat') && params.has('lng')) {
        selectedPlace.value = {
            name: params.get('city'),
            lat: parseFloat(params.get('lat')),
            lng: parseFloat(params.get('lng'))
        };
        searchInput.value = params.get('city');
    }
    
    // Show initial places immediately
    places.value = initializePlaces();
    dropdown.value = true;

    // Initialize Google Maps with async loading
    if (!window.google) {
        let script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=AIzaSyBxpUKfSJMC4_3xwLU73AmH-jszjexoriw&libraries=places`;
        script.async = true;
        script.onload = () => {
            initGoogleMaps();
        };
        document.head.appendChild(script);
    } else {
        initGoogleMaps();
    }

    // Add search trigger listener
    window.addEventListener('trigger-search', handleSearch);

    // Add listener for clear all
    window.addEventListener('clear-search-state', () => {
        searchInput.value = '';
        selectedPlace.value = null;
        date.value = null;
        isVisible.value = 'location';
        dropdown.value = true;
        places.value = initializePlaces();
    });
});

onUnmounted(() => {
    if (window.initMap) {
        delete window.initMap;
    }

    // Remove search trigger listener with proper function reference
    window.removeEventListener('trigger-search', handleSearch);
});

// Computed property for formatting the date range
const formatDateRange = computed(() => {
   if (!date.value || !Array.isArray(date.value)) return 'Dates';
   
   const [start, end] = date.value;
   if (!start) return 'Dates';
   
   // If start and end are the same date, only show one date
   if (end && start.getTime() === end.getTime()) {
       return formatDate(start);
   }
   
   if (!end) return formatDate(start);
   
   return `${formatDate(start)} - ${formatDate(end)}`;
});

// Format individual dates
function formatDate(date) {
   return date.toLocaleDateString('en-US', { 
       month: 'short', 
       day: 'numeric' 
   });
}

// Disable past dates
const disabledDate = (date) => {
   return date < new Date();
};

// Handle date changes
function handleDateChange(newDate) {
   if (newDate && Array.isArray(newDate) && newDate.length === 2) {
       dateDropdown.value = false;
   }
}


// Add this computed property
const minDate = computed(() => {
   const today = new Date();
   today.setHours(0, 0, 0, 0); // Set to start of day
   return today;
});

// Add handleSearch function
const handleSearch = () => {
   if (!selectedPlace.value) return;
   
   // Now emit the complete location data
   emit('update:location', {
       city: selectedPlace.value.name,
       lat: selectedPlace.value.lat,
       lng: selectedPlace.value.lng,
       start: date.value?.[0] ? formatForUrl(date.value[0]) : null,
       end: date.value?.[1] ? formatForUrl(date.value[1]) : null
   });
   
   const searchParams = {
       city: selectedPlace.value.name,
       searchType: 'inPerson',
       live: false,
       lat: selectedPlace.value.lat,
       lng: selectedPlace.value.lng
   };
   
   // Check current searchType
   const currentParams = new URLSearchParams(window.location.search);
   const currentSearchType = currentParams.get('searchType');

   // If we're switching from allEvents to inPerson, always redirect
   if (currentSearchType === 'allEvents') {
       let searchUrl = `/index/search?${new URLSearchParams(searchParams).toString()}`;
       window.location.href = searchUrl;
       return;
   }

   // Otherwise, proceed with normal logic
   if (window.location.pathname === '/index/search' && currentSearchType === 'inPerson') {
       // Emit filter update instead of redirecting
       window.dispatchEvent(new CustomEvent('filter-update', {
           detail: {
               type: 'location',
               value: searchParams
           }
       }));
       
       // Update URL without reload
       const params = new URLSearchParams(window.location.search);
       Object.entries(searchParams).forEach(([key, value]) => {
           params.set(key, value);
       });
       window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`);
       
       // Hide the search modal
       window.dispatchEvent(new CustomEvent('hide-search'));
   } else {
       // On other pages or different searchType, redirect
       let searchUrl = `/index/search?${new URLSearchParams(searchParams).toString()}`;
       window.location.href = searchUrl;
   }
   
   saveSearchData({ name: selectedPlace.value.name });
};

// Helper function for date formatting
const formatForUrl = (date) => {
    return date.toISOString().split('T')[0] + ' 00:00:00';
};

const clearDates = () => {
   date.value = null;
   dateDropdown.value = false;
   
   // Only trigger search if we have a selected place
   if (selectedPlace.value) {
       const searchParams = {
           city: selectedPlace.value.name,
           searchType: 'inPerson',
           live: false,
           lat: selectedPlace.value.lat,
           lng: selectedPlace.value.lng
       };

       // Check if we're on the search page
       if (window.location.pathname === '/index/search') {
           // Emit filter update instead of redirecting
           window.dispatchEvent(new CustomEvent('filter-update', {
               detail: {
                   type: 'location',
                   value: searchParams
               }
           }));
           
           // Update URL without reload
           const params = new URLSearchParams(window.location.search);
           Object.entries(searchParams).forEach(([key, value]) => {
               params.set(key, value);
           });
           
           // Remove date parameters
           params.delete('start');
           params.delete('end');
           
           window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`);
           
           // Hide the search modal
           window.dispatchEvent(new CustomEvent('hide-search'));
       } else {
           // On other pages, redirect as normal
           let searchUrl = `/index/search?${new URLSearchParams(searchParams).toString()}`;
           
           // Preserve other existing URL parameters
           const currentParams = new URLSearchParams(window.location.search);
           const paramsToPreserve = ['category', 'tags', 'price0', 'price1'];
           
           paramsToPreserve.forEach(param => {
               if (currentParams.has(param)) {
                   searchUrl += `&${param}=${currentParams.get(param)}`;
               }
           });
           
           window.location.href = searchUrl;
       }
   }
};

// Modify the watch effect
watch(isVisible, (newValue) => {
    if (newValue === 'location') {
        dropdown.value = true;
        // If there's no search input, show initial places
        if (!searchInput.value) {
            places.value = initializePlaces();
        } else {
            // If there is search input, update locations
            updateLocations();
        }
    }
});

// Add these methods in your script setup
const showLocationSection = () => {
    console.log(dropdown.value);
    isVisible.value = 'location';
    dropdown.value = true;
    console.log(dropdown.value);
    places.value = initializePlaces();
    console.log(dropdown.value);
};

const showDatesSection = () => {
    isVisible.value = 'dates';
    dropdown.value = false;
};

// Add this method to handle loading more months
const loadMoreMonths = () => {
    displayedMonths.value = 6;
};

// Add this computed property
const getCurrentMonthYear = computed(() => {
    const now = new Date();
    return {
        month: now.getMonth(),
        year: now.getFullYear()
    };
});

// Add method to clear state
const clearState = (isClearAll = false) => {
    console.log('Child clearState called, isClearAll:', isClearAll);
    
    if (isClearAll) {
        // Actually clear everything
        console.log('Clearing all state');
        searchInput.value = '';
        selectedPlace.value = null;
        date.value = null;
        emit('update:location', null);
    }
    
    isVisible.value = 'location';
    dropdown.value = true;
    places.value = initializePlaces();
    
    console.log('Child state after operation:', {
        searchInput: searchInput.value,
        selectedPlace: selectedPlace.value,
        date: date.value,
        isVisible: isVisible.value,
        dropdown: dropdown.value
    });
};

// Expose the method to the parent
defineExpose({ clearState });
</script>

<style>

.dp--arrow-btn-nav {
    display: none;
}

/* Remove the tabs at the top */
.dp__menu_inner .dp__menu_items {
   display: none !important;
}

/* Calendar styling */
.dp__calendar {
   width: 100% !important;

}

/* Header month/year styling */
.dp__month_year_wrap {
   font-size: 1.7rem;
   font-weight: 400;
}

/* Calendar header (days of week) */
.dp__calendar_header {
   color: #666;
   font-weight: normal;
   margin-bottom: 8px;
   font-size: 1.2rem;
}

.dp__calendar_row {
   margin: 0 !important;
   gap: 0 !important;
}

.dp__calendar_item {
   margin: 0 !important;
   padding: 0 !important;
   font-size: 1.4rem;
}

/* Calendar cells */
.dp__cell_inner {
   height: 45px;
   width: 45px;
   margin: 0 !important;
   padding: 0 !important;
   display: flex;
   align-items: center;
   justify-content: center;
   border-radius: 9999px;
   font-weight: normal;
   color: #333;
}

.dp__cell_disabled {
   opacity: 0.3;
   cursor: auto !important;
}

/* Hover state */
.dp__cell_inner:not(.dp--past):hover {
   border: 2px solid black !important;
   background: transparent !important;
   color: black !important;
}

/* Selected state */
.dp__active {
   background-color: black !important;
   color: white !important;
}

/* Range styling */
.dp__range_start,
.dp__range_end {
   background-color: black !important;
   color: white !important;
}

.dp__range_start {
   border-top-right-radius: 0 !important;
   border-bottom-right-radius: 0 !important;
}

.dp__range_end {
   border-top-left-radius: 0 !important;
   border-bottom-left-radius: 0 !important;
}

.dp__range_between {
   border-radius: 0 !important;
}

/* Navigation arrows */
.dp__arrow_bottom,
.dp__arrow_top {
   display: none;
}

/* Today's date */
.dp__today {
   border: none !important;
}

/* Calendar container */
.dp__main {
   border: none;
   box-shadow: none;
}

/* Remove borders */
.dp__calendar_header_separator {
   display: none;
}
.dp__theme_light {
   border: none !important;
}
.dp--header-wrap {
   margin-bottom: 1rem;
}
.dp__menu_inner.dp__flex_display {
   gap: 4rem;
}
.dp__calendar_next {
   margin-inline-start: 0 !important;
}

/* Add these styles for the calendar layout */
.dp__menu_inner.dp__flex_display {
    flex-direction: column !important;
    gap: 2rem !important;
}

.dp__calendar_next {
    margin-top: 2rem !important;
}

/* Optional: Add smooth transition */
.dp__calendar {
    transition: all 0.3s ease;
}
</style>
