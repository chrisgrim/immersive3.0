<template>
    <div class="relative flex w-full gap-2 border border-slate-300 rounded-full" 
    :class="{ 'bg-neutral-200': dropdown || dateDropdown }">
       <!-- Location Search -->
       <div class="flex-1" v-click-outside="closeLocationDropdown">
           <svg class="absolute top-8 left-8 w-8 h-8 fill-black z-[1002]">
               <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
           </svg>
           <input 
               ref="loc"
               class="relative text-1xl rounded-full h-full pl-24 bg-transparent w-full font-bold z-40 focus:border-none focus:rounded-full focus:bg-white focus:shadow-custom-7 placeholder-black"
               v-model="searchInput"
               placeholder="Search by City"
               @input="updateLocations"
               @focus="dropdown=true"
               autocomplete="false"
               onfocus="value = ''" 
               type="text">
           
           <!-- Location Dropdown -->
           <ul 
               class="absolute bg-white w-full mx-0 overflow-hidden mt-8 p-8 list-none rounded-5xl shadow-custom-7" 
               v-if="dropdown"
               @click.stop>
               <li 
                   class="py-4 px-8 flex items-center gap-8 hover:bg-neutral-100" 
                   v-for="place in places"
                   :key="place.place_id"
                   @click.stop="selectLocation(place)">
                   <img 
                       class="w-8" 
                       src="/storage/images/vendor/leaflet/dist/marker-icon-2x.png">
                   {{place.description}}
               </li>
           </ul>
       </div>

       <!-- Date Search -->
       <div class="" v-click-outside="closeDateDropdown">
           <button 
               @click.stop="toggleDateDropdown"
               class="text-1xl rounded-full p-7 px-12 font-bold hover:shadow-custom-7 flex items-center gap-2 location-search-date-btn"
               :class="{ 'bg-white shadow-custom-7': dateDropdown, 'bg-transparent': !dateDropdown }"
           >
               <span v-if="date">{{ formatDateRange }}</span>
               <span v-else>Dates</span>
           </button>

           <!-- Date Picker Dropdown -->
           <div 
               v-if="dateDropdown" 
               class="absolute -left-0 w-full mt-8 bg-white rounded-5xl shadow-custom-7 p-8 z-40"
               @click.stop
           >
               <VueDatePicker
                   v-model="date"
                   range
                   multi-calendars
                   disable-year-select
                   :enable-time-picker="false"
                   :dark="isDark"
                   :timezone="tz"
                   :min-date="minDate"
                   inline
                   auto-apply
                   :max-date="maxDate"
                   @update:model-value="handleDateChange"
                   month-name-format="long"
                   hide-offset-dates
                   week-start="0"
                   :key="'datepicker-' + (isProduction ? 'prod' : 'dev')"
               />
               <div class="flex justify-between items-center mt-8 pt-8 border-t border-gray-200">
                   <button 
                       v-if="date"
                       @click="clearDates" 
                       class="text-black underline font-semibold hover:text-gray-600"
                   >
                       Clear
                   </button>
                   <button 
                       @click="closeDateDropdown" 
                       class="text-black font-semibold hover:text-gray-600"
                       :class="{ 'ml-auto': !date }"
                   >
                       Cancel
                   </button>
               </div>
           </div>
       </div>

       <!-- Search Button -->
       <button 
           @click="handleSearch"
           class="rounded-full px-12 my-2 font-semibold transition-colors"
           :class="[
               selectedPlace ? 
               'bg-default-red text-white cursor-pointer hover:bg-red-600' : 
               'bg-black text-white cursor-not-allowed'
           ]"
       >
           Search
       </button>

       <div id="places" />
   </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import eventStore from '@/Stores/EventStore';

// Add props for initial date values
const props = defineProps({
    initialStartDate: {
        type: String,
        default: null
    },
    initialEndDate: {
        type: String,
        default: null
    }
});

// Add this to detect production mode
const isProduction = import.meta.env.PROD;

// Existing date-related refs
const dateDropdown = ref(false);
const dropdown = ref(false);
const searchInput = ref('');
const places = ref(initializePlaces());
const date = ref(null);

// Add a watcher to update date when props change
watch(() => [props.initialStartDate, props.initialEndDate], ([newStartDate, newEndDate]) => {
    if (newStartDate && newEndDate) {
        try {
            const startDate = new Date(newStartDate);
            const endDate = new Date(newEndDate);
            
            // Make sure these are valid dates before setting
            if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
                date.value = [startDate, endDate];
            }
        } catch (e) {
            console.error('Error parsing dates from props:', e);
        }
    }
}, { immediate: true });

// Location search functionality
let autoComplete;
let service;

// Add these new refs to store selected location data
const selectedPlace = ref(null);

// Define emits and expose methods for parent component
const emit = defineEmits(['location-updated']);

// Expose the toggleDateDropdown method so it can be called from parent
defineExpose({
    openDateDropdown: () => {
        // Force date synchronization before opening
        const storeState = eventStore.state;
        if (storeState.dates.start && storeState.dates.end) {
            try {
                const startDate = new Date(storeState.dates.start);
                const endDate = new Date(storeState.dates.end);
                
                if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
                    // Update with fresh values from store
                    date.value = [startDate, endDate];
                }
            } catch (e) {
                console.error('Error in openDateDropdown:', e);
            }
        } else if (props.initialStartDate && props.initialEndDate) {
            try {
                const startDate = new Date(props.initialStartDate);
                const endDate = new Date(props.initialEndDate);
                
                if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
                    // Update with fresh values from props
                    date.value = [startDate, endDate];
                }
            } catch (e) {
                console.error('Error in openDateDropdown:', e);
            }
        }
        
        // Now open the dropdown
        dateDropdown.value = true;
        dropdown.value = false;
    }
});

function initializePlaces() {
   return [
       { place_id: 'ChIJOwg_06VPwokRYv534QaPC8g', description: 'New York, NY, USA' },
       { place_id: 'ChIJE9on3F3HwoAR9AhGJW_fL-I', description: 'Los Angeles, CA, USA' },
       { place_id: 'ChIJIQBpAG2ahYAR_6128GcTUEo', description: 'San Francisco, CA, USA' }
   ];
}

const updateLocations = () => {
   dropdown.value = searchInput.value.length ? true : false;
   autoComplete.getPlacePredictions({ input: searchInput.value, types: ['(cities)'] }, data => {
       places.value = data;
   });
};

const selectLocation = (location) => {
   service.getDetails({ placeId: location.place_id }, data => {
       setPlace(data);
   });
};

const setPlace = (place) => {
   const lat = place.geometry.location.lat();
   const lng = place.geometry.location.lng();
   
   selectedPlace.value = {
       name: place.name,
       lat: lat,
       lng: lng
   };
   
   searchInput.value = place.name;
   dropdown.value = false;
   
   // Instead of opening the date dropdown, trigger search immediately
   handleSearch();
};

const saveSearchData = (place) => {
//    axios.post('/search/storedata', { type: 'location', name: place.name });
};

const initGoogleMaps = () => {
   autoComplete = new google.maps.places.AutocompleteService();
   service = new google.maps.places.PlacesService(document.getElementById("places"));
};

// Initialize from URL parameters
onMounted(() => {
   // Sync with EventStore state
   const storeState = eventStore.state;
   
   // Initialize location if present in store
   if (storeState.location.city && storeState.location.lat && storeState.location.lng) {
       selectedPlace.value = {
           name: storeState.location.city,
           lat: storeState.location.lat,
           lng: storeState.location.lng
       };
       searchInput.value = storeState.location.city;
   }
   
   // Initialize dates if present in store
   if (storeState.dates.start || storeState.dates.end) {
       const startDate = storeState.dates.start ? new Date(storeState.dates.start) : null;
       const endDate = storeState.dates.end ? new Date(storeState.dates.end) : null;
       
       // Set the date range
       if (startDate && endDate) {
           date.value = [startDate, endDate];
       } else if (startDate) {
           date.value = [startDate, startDate];
       }
   }

   // Better Google Maps initialization to prevent duplicate loading
   if (typeof google === 'undefined' || !google.maps) {
       // Only create script if Google Maps is not already loaded
       if (!document.getElementById('google-maps-script')) {
           let script = document.createElement('script');
           script.id = 'google-maps-script';
           script.src = `https://maps.googleapis.com/maps/api/js?key=AIzaSyBxpUKfSJMC4_3xwLU73AmH-jszjexoriw&libraries=places&callback=initMap`;
           script.async = true;
           script.defer = true;
           document.head.appendChild(script);
       }

       window.initMap = () => {
           if (!window.googleMapsInitialized) {
               initGoogleMaps();
               window.googleMapsInitialized = true;
           }
       };
   } else {
       // Google Maps already loaded, initialize directly
       initGoogleMaps();
   }

   // Add a window event listener for popstate to update UI when URL changes
   window.addEventListener('popstate', () => {
       const params = new URLSearchParams(window.location.search);
       if (params.has('city')) {
           searchInput.value = params.get('city');
       }
   });

   // Add a MutationObserver to detect DOM changes that might affect the URL
   const observer = new MutationObserver(() => {
       // Check if we have dates in storage but not in URL
       const currentUrl = window.location.href;
       const hasDateParams = currentUrl.includes('start=') && currentUrl.includes('end=');
       const storedStartDate = sessionStorage.getItem('ei_search_start_date');
       const storedEndDate = sessionStorage.getItem('ei_search_end_date');
       
       if (!hasDateParams && storedStartDate && storedEndDate) {
           const restoredParams = new URLSearchParams(window.location.search);
           restoredParams.set('start', storedStartDate);
           restoredParams.set('end', storedEndDate);
           const restoredUrl = `${window.location.pathname}?${restoredParams.toString()}`;
           window.history.pushState({}, '', restoredUrl);
       }
   });

   // Start observing
   observer.observe(document.body, { 
       childList: true, 
       subtree: true,
       attributes: true 
   });

   // Cleanup on unmount
   onUnmounted(() => {
       observer.disconnect();
   });
});

onUnmounted(() => {
   if (window.initMap) {
       delete window.initMap;
   }
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
       // Don't close the dropdown or trigger search automatically
       // dateDropdown.value = false;
       
       // Store the selected dates but don't auto-search
       // The search will only happen when the user clicks the Search button
       console.log('Date selection changed:', newDate);
       
       const startDate = newDate[0];
       const endDate = newDate[1];
       
       // Format dates for storage
       const formatForUrl = (date) => {
           return date.toISOString().split('T')[0] + ' 00:00:00';
       };
       
       // Update local state only - don't trigger store update yet
       // That will happen when Search is clicked
   }
}

// Toggle dropdowns
function toggleDateDropdown() {
   dateDropdown.value = !dateDropdown.value;
   dropdown.value = false;
   
   // Synchronize date values when opening the dropdown
   if (dateDropdown.value) {
       // Check if parent component has dates and we don't
       const params = new URLSearchParams(window.location.search);
       if (params.has('start') && params.has('end') && (!date.value || date.value === null)) {
           const startDate = new Date(params.get('start'));
           const endDate = new Date(params.get('end'));
           if (startDate && endDate) {
               // Update our local date ref with the parent's values
               date.value = [startDate, endDate];
           }
       }
   }
}

function closeDateDropdown() {
   dateDropdown.value = false;
}

function closeLocationDropdown() {
   dropdown.value = false;
}

// Add this computed property
const minDate = computed(() => {
   const today = new Date();
   today.setHours(0, 0, 0, 0); // Set to start of day
   return today;
});

// Modify handleSearch function to use EventStore
const handleSearch = () => {
   console.log('handleSearch called');
   if (!selectedPlace.value) return;
   
   // Ensure searchInput is updated with the latest selected place name
   searchInput.value = selectedPlace.value.name;
   
   // Make sure we have valid coordinates - fallback to New York coordinates if missing
   const lat = selectedPlace.value.lat || 40.7127753;
   const lng = selectedPlace.value.lng || -74.0059728;
   
   // Create updates for the store
   const locationUpdate = {
       city: selectedPlace.value.name,
       searchType: 'inPerson',
       live: false,
       lat: lat,
       lng: lng
   };
   
   // Create date updates if we have dates
   const dateUpdate = {};
   if (date.value && Array.isArray(date.value) && date.value[0]) {
       const [start, end] = date.value;
       const formatForUrl = (date) => {
           return date.toISOString().split('T')[0] + ' 00:00:00';
       };
       
       dateUpdate.start = formatForUrl(start);
       dateUpdate.end = end ? formatForUrl(end) : formatForUrl(start);
   }
   
   // Close dropdowns before updating
   dateDropdown.value = false;
   dropdown.value = false;

   // Emit location-updated event to parent (for UI updates)
   emit('location-updated', {
       city: locationUpdate.city,
       startDate: dateUpdate.start || null,
       endDate: dateUpdate.end || null,
       lat: lat,
       lng: lng
   });

   // Check current path
   const isSearchPage = window.location.pathname === '/index/search';
   console.log('Current path:', window.location.pathname, 'Is search page:', isSearchPage);
   
   // Check current searchType (debug)
   const currentSearchType = eventStore.state.location.searchType;
   console.log('Current searchType:', currentSearchType, 'Setting to: inPerson');

   // If we're switching from allEvents to inPerson, always redirect
   if (currentSearchType === 'allEvents') {
       // Update store with new values but don't trigger fetch
       eventStore.update({
           location: locationUpdate,
           dates: dateUpdate
       }, false);
       
       // Redirect to search page
       window.location.href = `/index/search?${new URLSearchParams(window.location.search).toString()}`;
       return;
   }

   // Otherwise, proceed with normal logic
   if (isSearchPage) {
       // Update the store with our new values
       eventStore.update({
           location: locationUpdate,
           dates: dateUpdate,
           // Reset to page 1 on new search
           page: 1
       });
       
       // Hide the search modal
       window.dispatchEvent(new CustomEvent('hide-search'));
   } else {
       // Update store without triggering fetch
       eventStore.update({
           location: locationUpdate,
           dates: dateUpdate
       }, false);
       
       // Redirect to search page with explicit searchType and live parameter
       const params = new URLSearchParams();
       params.set('city', locationUpdate.city);
       params.set('lat', locationUpdate.lat.toString());
       params.set('lng', locationUpdate.lng.toString());
       params.set('searchType', 'inPerson');
       params.set('live', 'false');
       
       // Add date params if we have them
       if (dateUpdate.start) params.set('start', dateUpdate.start);
       if (dateUpdate.end) params.set('end', dateUpdate.end);
       
       console.log('Redirecting to:', `/index/search?${params.toString()}`);
       window.location.href = `/index/search?${params.toString()}`;
   }
   
   saveSearchData({ name: selectedPlace.value.name });
};

// Modify clearDates to use EventStore
const clearDates = () => {
   // 1. Clear the local date value
   date.value = null;
   
   // 2. Close the date dropdown
   dateDropdown.value = false;
   
   // Only proceed if we have a selected place
   if (selectedPlace.value) {
       // 3. Update the EventStore to clear dates
       eventStore.clearDates();
       
       // 4. Emit to parent with null dates (for UI updates)
       emit('location-updated', {
           city: selectedPlace.value.name,
           startDate: null,
           endDate: null,
           lat: selectedPlace.value.lat,
           lng: selectedPlace.value.lng
       });

       // 5. Hide the search modal if we're on the search page
       if (window.location.pathname === '/index/search') {
           window.dispatchEvent(new CustomEvent('hide-search'));
       }
   }
};
</script>

<style>
/* Add a scoping class to all styles and make them !important */
.search-container .dp__menu_inner .dp__menu_items {
   display: none !important;
}

/* Calendar styling */
.search-container .dp__calendar {
   width: 100% !important;
}

/* Header month/year styling */
.search-container .dp__month_year_wrap {
   font-size: 1.7rem !important;
   font-weight: 400 !important;
}

/* Calendar header (days of week) */
.search-container .dp__calendar_header {
   color: #666 !important;
   font-weight: normal !important;
   margin-bottom: 8px !important;
   font-size: 1.2rem !important;
}

.search-container .dp__calendar_row {
   margin: 0 !important;
   gap: 0 !important;
}

.search-container .dp__calendar_item {
   margin: 0 !important;
   padding: 0 !important;
   font-size: 1.4rem !important;
}

/* Calendar cells */
.search-container .dp__cell_inner {
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

.search-container .dp__cell_disabled {
   opacity: 0.3 !important;
   cursor: auto !important;
}

/* Hover state */
.search-container .dp__cell_inner:not(.dp--past):hover {
   border: 2px solid black !important;
   background: transparent !important;
   color: black !important;
}

/* Selected state */
.search-container .dp__active {
   background-color: black !important;
   color: white !important;
}

/* Range styling */
.search-container .dp__range_start,
.search-container .dp__range_end {
   background-color: black !important;
   color: white !important;
}

.search-container .dp__range_start {
   border-top-right-radius: 0 !important;
   border-bottom-right-radius: 0 !important;
}

.search-container .dp__range_end {
   border-top-left-radius: 0 !important;
   border-bottom-left-radius: 0 !important;
}

.search-container .dp__range_between {
   border-radius: 0 !important;
}

/* Navigation arrows */
.search-container .dp__arrow_bottom,
.search-container .dp__arrow_top {
   display: none !important;
}

/* Today's date */
.search-container .dp__today {
   border: none !important;
}

/* Calendar container */
.search-container .dp__main {
   border: none !important;
   box-shadow: none !important;
}

/* Remove borders */
.search-container .dp__calendar_header_separator {
   display: none !important;
}

.search-container .dp__theme_light {
   border: none !important;
}

.search-container .dp__header-wrap {
   margin-bottom: 1rem !important;
}

.search-container .dp__menu_inner.dp__flex_display {
   gap: 4rem !important;
}

.search-container .dp__calendar_next {
   margin-inline-start: 0 !important;
}
</style>