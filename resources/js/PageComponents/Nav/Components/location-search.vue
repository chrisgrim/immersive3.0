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
               class="text-1xl rounded-full p-7 px-12 font-bold hover:shadow-custom-7 flex items-center gap-2"
               :class="{ 'bg-white shadow-custom-7': dateDropdown, 'bg-transparent': !dateDropdown }"
           >
               <span v-if="date">{{ formatDateRange }}</span>
               <span v-else>Dates</span>
           </button>

           <!-- Date Picker Dropdown -->
           <div 
               v-if="dateDropdown" 
               class="absolute -left-0 w-[calc(100%+10.5rem)] mt-8 bg-white rounded-5xl shadow-custom-7 p-8 z-40"
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
import { ref, onMounted, onUnmounted, computed } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import axios from 'axios';

// Existing date-related refs
const dateDropdown = ref(false);
const dropdown = ref(false);
const searchInput = ref('');
const places = ref(initializePlaces());
const date = ref(null);

// Location search functionality
let autoComplete;
let service;

// Add these new refs to store selected location data
const selectedPlace = ref(null);

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
   selectedPlace.value = {
       name: place.name,
       lat: place.geometry.location.lat(),
       lng: place.geometry.location.lng()
   };
   searchInput.value = place.name;
   dropdown.value = false;
   
   // Add a small delay before opening the date dropdown
   setTimeout(() => {
       dateDropdown.value = true;
   }, 100);
};

const saveSearchData = (place) => {
   axios.post('/search/storedata', { type: 'location', name: place.name });
};

const initGoogleMaps = () => {
   autoComplete = new google.maps.places.AutocompleteService();
   service = new google.maps.places.PlacesService(document.getElementById("places"));
};

// Initialize from URL parameters
onMounted(() => {
   const params = new URLSearchParams(window.location.search);
   
   // Initialize location if present
   if (params.has('city') && params.has('lat') && params.has('lng')) {
       selectedPlace.value = {
           name: params.get('city'),
           lat: parseFloat(params.get('lat')),
           lng: parseFloat(params.get('lng'))
       };
       searchInput.value = params.get('city');
   }
   
   // Initialize dates if present
   if (params.has('start') || params.has('end')) {
       const start = params.get('start');
       const end = params.get('end');
       
       // Convert the date strings to Date objects
       const startDate = start ? new Date(start) : null;
       const endDate = end ? new Date(end) : null;
       
       // Set the date range
       if (startDate && endDate) {
           date.value = [startDate, endDate];
       } else if (startDate) {
           date.value = [startDate, startDate];
       }
   }

   // Initialize Google Maps
   let script = document.createElement('script');
   script.src = `https://maps.googleapis.com/maps/api/js?key=AIzaSyBxpUKfSJMC4_3xwLU73AmH-jszjexoriw&libraries=places&callback=initMap`;
   script.async = true;
   script.defer = true;
   document.head.appendChild(script);

   window.initMap = initGoogleMaps;
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
       dateDropdown.value = false;
   }
}

// Toggle dropdowns
function toggleDateDropdown() {
   dateDropdown.value = !dateDropdown.value;
   dropdown.value = false;
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

// Add handleSearch function
const handleSearch = () => {
   if (!selectedPlace.value) return;
   
   const searchParams = {
       city: selectedPlace.value.name,
       searchType: 'inPerson',
       live: false,
       lat: selectedPlace.value.lat,
       lng: selectedPlace.value.lng
   };
   
   // Add date parameters if selected
   if (date.value && Array.isArray(date.value) && date.value[0]) {
       const [start, end] = date.value;
       const formatForUrl = (date) => {
           return date.toISOString().split('T')[0] + ' 00:00:00';
       };
       
       searchParams.start = formatForUrl(start);
       if (end) {
           searchParams.end = formatForUrl(end);
       }
   }

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
</script>

<style>

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
</style>