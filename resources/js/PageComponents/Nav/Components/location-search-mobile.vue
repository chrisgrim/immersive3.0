<template>
    <div class="w-full h-full pb-8 flex flex-col gap-8">
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
                <p class="font-bold">{{ searchInput || 'New York' }}</p>
            </div>
        </div>
        <div 
            v-else
            class="flex-grow relative w-full border shadow-custom-6 rounded-4xl bg-white p-8 overflow-auto">
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
                        class="relative text-4xl p-8 w-full font-bold z-40 bg-transparent focus:border-none focus:outline-none placeholder-slate-400 touch-manipulation"
                        v-model="searchInput"
                        placeholder="Search by City"
                        @input="updateLocations"
                        @focus="dropdown=true"
                        autocomplete="off"
                        type="text">
                </div>
                
                <!-- Location Dropdown -->
                <ul 
                    class="bg-white w-full mx-0 overflow-hidden py-8 list-none" 
                    v-if="dropdown"
                    @click.stop>
                    <li 
                        class="text-2xl font-medium py-4 px-8 flex items-center gap-8 hover:bg-neutral-100" 
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
                <p class="font-bold">{{ date ? formatDateRange : 'Add Dates' }}</p>
            </div>
        </div>
        <div 
            v-else
            class="flex-grow relative w-full border shadow-custom-6 rounded-4xl bg-white p-8 overflow-auto">
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
            <div v-if="displayedMonths === 3" class="w-full flex justify-center mt-8 border-t pt-8 mb-20">
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
import eventStore from '@/Stores/EventStore.vue';

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

// EventStore subscription
const unsubscribe = ref(null);

// Initialize dark mode and timezone variables for datepicker
const isDark = ref(false);
const tz = computed(() => {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
});

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
   
   // Update both parent component and EventStore
   emit('update:location', place.name);
   
   // Update EventStore with location (but don't fetch events yet)
   eventStore.update({
       location: {
           city: place.name,
           lat: place.geometry.location.lat(),
           lng: place.geometry.location.lng(),
           searchType: 'inPerson',
           live: false
       }
   }, false);
   
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

// Initialize from URL parameters and EventStore
onMounted(() => {    
    // Subscribe to EventStore updates
    unsubscribe.value = eventStore.subscribe(state => {
        // Update from EventStore if values exist
        if (state.location.city) {
            searchInput.value = state.location.city;
            selectedPlace.value = {
                name: state.location.city,
                lat: state.location.lat,
                lng: state.location.lng
            };
        }
        
        // Update dates from EventStore
        if (state.dates.start && state.dates.end) {
            try {
                const startDate = new Date(state.dates.start);
                const endDate = new Date(state.dates.end);
                
                if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
                    date.value = [startDate, endDate];
                }
            } catch (e) {
                console.error('Error parsing dates from EventStore:', e);
            }
        }
    });
    
    // Set initial city if provided by props
    if (props.initialCity) {
        searchInput.value = props.initialCity;
    }

    // Set initial dates if provided by props
    if (props.initialStartDate && props.initialEndDate) {
        date.value = [new Date(props.initialStartDate), new Date(props.initialEndDate)];
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

    // Clean up EventStore subscription
    if (unsubscribe.value) {
        unsubscribe.value();
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

// Handle date changes
function handleDateChange(newDate) {
   if (newDate && Array.isArray(newDate) && newDate.length === 2) {
       dateDropdown.value = false;
       
       // Format dates for EventStore
       const formatForStore = (date) => {
           return date.toISOString().split('T')[0] + ' 00:00:00';
       };
       
       // Update EventStore with dates (but don't fetch yet)
       eventStore.update({
           dates: {
               start: formatForStore(newDate[0]),
               end: formatForStore(newDate[1])
           }
       }, false);
       
       // Also update for parent component
       if (selectedPlace.value) {
           emit('update:location', {
               city: selectedPlace.value.name,
               lat: selectedPlace.value.lat,
               lng: selectedPlace.value.lng,
               start: formatForStore(newDate[0]),
               end: formatForStore(newDate[1])
           });
       }
   }
}

// Add this computed property
const minDate = computed(() => {
   const today = new Date();
   today.setHours(0, 0, 0, 0); // Set to start of day
   return today;
});

// Add handleSearch function that uses EventStore
const handleSearch = () => {
    if (!selectedPlace.value) return;
    
    // First update the EventStore with final state
    eventStore.update({
        source: 'initialSearch',
        location: {
            city: selectedPlace.value.name,
            lat: selectedPlace.value.lat,
            lng: selectedPlace.value.lng,
            searchType: 'inPerson',
            live: false,
            NElat: null,
            NElng: null,
            SWlat: null,
            SWlng: null
        },
        dates: {
            start: date.value?.[0] ? formatForUrl(date.value[0]) : null,
            end: date.value?.[1] ? formatForUrl(date.value[1]) : null
        }
    }, true);
    
    // Hide the search modal
    window.dispatchEvent(new CustomEvent('hide-search'));
    
    // Save search data
    saveSearchData({ name: selectedPlace.value.name });
};

// Helper function for date formatting
const formatForUrl = (date) => {
    return date.toISOString().split('T')[0] + ' 00:00:00';
};

// Clear dates using EventStore
const clearDates = () => {
   date.value = null;
   dateDropdown.value = false;
   
   // Update EventStore
   eventStore.clearDates();
   
   // Only emit update if we have a selected place
   if (selectedPlace.value) {
       emit('update:location', {
           city: selectedPlace.value.name,
           lat: selectedPlace.value.lat,
           lng: selectedPlace.value.lng,
           start: null,
           end: null
       });
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
    isVisible.value = 'location';
    dropdown.value = true;
    places.value = initializePlaces();
};

const showDatesSection = () => {
    isVisible.value = 'dates';
    dropdown.value = false;
};

// Add this method to handle loading more months
const loadMoreMonths = () => {
    displayedMonths.value = 6;
};

// Add method to clear state
const clearState = (isClearAll = false) => {
    if (isClearAll) {
        // Actually clear everything
        searchInput.value = '';
        selectedPlace.value = null;
        date.value = null;
        
        // Also clear in EventStore
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
        
        emit('update:location', null);
    }
    
    isVisible.value = 'location';
    dropdown.value = true;
    places.value = initializePlaces();
};

// Expose the method to the parent
defineExpose({ clearState });
</script>

<style>
.dp--arrow-btn-nav {
    display: none !important;
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
   font-size: 1.7rem !important;
   font-weight: 400 !important;
}

/* Calendar header (days of week) */
.dp__calendar_header {
   color: #666 !important;
   font-weight: normal !important;
   margin-bottom: 8px !important;
   font-size: 1.2rem !important;
}

.dp__calendar_row {
   margin: 0 !important;
   gap: 0 !important;
}

.dp__calendar_item {
   margin: 0 !important;
   padding: 0 !important;
   font-size: 1.4rem !important;
}

/* Calendar cells */
.dp__cell_inner {
   height: 45px !important;
   width: 45px !important;
   margin: 0 !important;
   padding: 0 !important;
   display: flex !important;
   align-items: center !important;
   justify-content: center !important;
   border-radius: 9999px !important;
   font-weight: normal !important;
   color: #333 !important;
}

.dp__cell_disabled {
   opacity: 0.3 !important;
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
   display: none !important;
}

/* Today's date */
.dp__today {
   border: none !important;
}

/* Calendar container */
.dp__main {
   border: none !important;
   box-shadow: none !important;
}

/* Remove borders */
.dp__calendar_header_separator {
   display: none !important;
}

.dp__theme_light {
   border: none !important;
}

.dp--header-wrap {
   margin-bottom: 1rem !important;
}

.dp__menu_inner.dp__flex_display {
   gap: 4rem !important;
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
    transition: all 0.3s ease !important;
}

/* Remove these styles as we want the natural browser behavior like the share modal */
/* 
.pb-safe {
    padding-bottom: max(20px, env(safe-area-inset-bottom, 20px) + 12px);
}

@media (orientation: landscape) {
    .pb-safe {
        padding-bottom: 20px;
    }
}

.dp__menu_inner + div,
.w-full.bg-white.flex.p-12.mb-20 {
    padding-bottom: max(20px, env(safe-area-inset-bottom, 20px) + 12px) !important;
}
*/

/* Add space at the bottom of content to prevent it getting hidden */
@media screen and (max-width: 768px) {
    .dp__menu_inner + div {
        margin-bottom: 40px;
    }
}

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
