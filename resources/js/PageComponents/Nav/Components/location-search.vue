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
                   class="py-4 px-8 flex items-center gap-8 hover:bg-neutral-100 group" 
                   v-for="place in places"
                   :key="place.place_id"
                   @click.stop="selectLocation(place)">
                   <div class="w-20 h-20 flex items-center justify-center bg-neutral-100 rounded-xl">
                       <svg class="w-14 h-14 transition-all duration-500 group-hover:fill-black group-hover:stroke-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                           <path d="M12 22s-8-5-8-11a8 8 0 1 1 16 0c0 6-8 11-8 11z"></path>
                           <circle cx="12" cy="11" r="3"></circle>
                       </svg>
                   </div>
                   <span class="transition-all group-hover:font-medium">{{place.description}}</span>
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
               (selectedPlace || date) ? 
               'bg-default-red text-white cursor-pointer hover:bg-red-600' : 
               'bg-black text-white cursor-not-allowed opacity-50'
           ]"
       >
           Search
       </button>
   </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'

// Props
const props = defineProps({
    initialStartDate: {
        type: String,
        default: null
    },
    initialEndDate: {
        type: String,
        default: null
    },
    initialCity: {
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
const isDark = ref(false); // For date picker theme

// Use a computed property to derive dates from props
const propsDateRange = computed(() => {
  if (props.initialStartDate && props.initialEndDate) {
    try {
      const startDate = new Date(props.initialStartDate);
      const endDate = new Date(props.initialEndDate);
      
      // Make sure these are valid dates before returning
      if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
        return [startDate, endDate];
      }
    } catch (e) {
      console.error('Error parsing dates from props:', e);
    }
  }
  return null;
});

// When you need to use the date, check both local state and props
const effectiveDate = computed(() => {
  // First priority: user selected date
  if (date.value) return date.value;
  
  // Second priority: dates from props
  if (propsDateRange.value) return propsDateRange.value;
  
  // Default: no date
  return null;
});

// Location search functionality
let autoComplete;

// Add these new refs to store selected location data
const selectedPlace = ref(null);

// Update emits to include close-search
const emit = defineEmits(['location-updated', 'search', 'close-search']);

// Expose the toggleDateDropdown method so it can be called from parent
defineExpose({
    openDateDropdown: () => {
        // Force the date dropdown to open
        dateDropdown.value = true;
        dropdown.value = false;

        // Check props first
        if (props.initialStartDate && props.initialEndDate) {
            try {
                const startDate = new Date(props.initialStartDate);
                const endDate = new Date(props.initialEndDate);
                if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
                    date.value = [startDate, endDate];
                }
            } catch (e) {
                console.error('Error in openDateDropdown:', e);
            }
        }
    }
});

function initializePlaces() {
   return [
       { place_id: 'ChIJOwg_06VPwokRYv534QaPC8g', description: 'New York, NY' },
       { place_id: 'ChIJE9on3F3HwoAR9AhGJW_fL-I', description: 'Los Angeles, CA' },
       { place_id: 'ChIJdd4hrwug2EcRmSrV3Vo6llI', description: 'London, UK' },
       { place_id: 'ChIJIQBpAG2ahYAR_6128GcTUEo', description: 'San Francisco, CA' },
       { place_id: 'ChIJzxcfI6PYa4cR1jaKJ_j0jhE', description: 'Denver, CO' }
   ];
}

const updateLocations = async () => {
   dropdown.value = searchInput.value.length ? true : false;
   
   if (!searchInput.value) {
       places.value = initializePlaces();
       return;
   }
   
   try {
       if (autoComplete) {
           const { predictions } = await autoComplete.getPlacePredictions({
               input: searchInput.value,
               types: ['(cities)']
           });
           places.value = predictions || initializePlaces();
       }
   } catch (error) {
       console.error('Error fetching place predictions:', error);
       places.value = initializePlaces();
   }
};

const selectLocation = async (location) => {
   try {
       const { Place } = await google.maps.importLibrary("places");
       const placeResult = new Place({ id: location.place_id });
       
       // Fetch the necessary fields
       await placeResult.fetchFields({
           fields: ["displayName", "formattedAddress", "location"]
       });
       
       setPlace(placeResult);
   } catch (error) {
       console.error('Error fetching place details:', error);
   }
};

const setPlace = (place) => {
   // The new Place API uses camelCase and different property structure
   // location might be accessed as an object with lat/lng properties or methods
   
   let lat = null;
   let lng = null;
   
   // Check if location exists and determine if it's an object or has accessor methods
   if (place.location) {
       if (typeof place.location.lat === 'function') {
           // Old API approach with methods
           lat = place.location.lat();
           lng = place.location.lng();
       } else if (typeof place.location.lat === 'number') {
           // New API approach with properties
           lat = place.location.lat;
           lng = place.location.lng;
       }
   } else if (place.geometry && place.geometry.location) {
       // Fallback to old structure
       lat = place.geometry.location.lat();
       lng = place.geometry.location.lng();
   }
   
   // Format the place name
   let placeName = place.displayName?.text || place.formattedAddress || place.name || "Unknown location";
   
   // For US locations, remove ", USA" if present
   if (placeName.endsWith(", USA")) {
       placeName = placeName.replace(", USA", "");
   }
   
   // For other locations, ensure we keep the country
   // Store the formatted place name
   selectedPlace.value = {
       name: placeName,
       lat: lat,
       lng: lng
   };
   
   searchInput.value = selectedPlace.value.name;
   dropdown.value = false;
   
   // Auto-submit for location selection
   handleSearch();
};

const saveSearchData = (place) => {
//    axios.post('/search/storedata', { type: 'location', name: place.name });
};

const initGoogleMaps = async () => {
   try {
       // Import the places library
       const { AutocompleteService } = await google.maps.importLibrary("places");
       autoComplete = new AutocompleteService();
   } catch (error) {
       console.error('Error initializing Google Maps Places API:', error);
   }
};

// Initialize from URL parameters
onMounted(() => {
    // Initialize city from URL or props
    const params = new URLSearchParams(window.location.search);
    if (params.get('city')) {
        searchInput.value = params.get('city');
        selectedPlace.value = {
            name: params.get('city'),
            lat: parseFloat(params.get('lat')),
            lng: parseFloat(params.get('lng'))
        };
    } else if (props.initialCity) {
        searchInput.value = props.initialCity;
    }

    // Initialize dates from props if available
    if (props.initialStartDate && props.initialEndDate) {
        try {
            const startDate = new Date(props.initialStartDate);
            const endDate = new Date(props.initialEndDate);
            
            if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
                date.value = [startDate, endDate];
            }
        } catch (e) {
            console.error('Error parsing initial dates:', e);
        }
    }

    // Initialize Google Maps
    if (typeof google === 'undefined' || !google.maps) {
        if (!document.getElementById('google-maps-script')) {
            let script = document.createElement('script');
            script.id = 'google-maps-script';
            script.src = `https://maps.googleapis.com/maps/api/js?key=AIzaSyBxpUKfSJMC4_3xwLU73AmH-jszjexoriw&libraries=places&callback=initMap&loading=async`;
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }

        window.initMap = async () => {
            if (!window.googleMapsInitialized) {
                await initGoogleMaps();
                window.googleMapsInitialized = true;
            }
        };
    } else {
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

// For the formatted display, use the effective date
const formatDateRange = computed(() => {
  const currentDate = effectiveDate.value;
  
  if (!currentDate || !Array.isArray(currentDate)) return 'Dates';
  
  const [start, end] = currentDate;
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

// Add timezone computed property for date picker
const tz = computed(() => {
   return Intl.DateTimeFormat().resolvedOptions().timeZone;
});

// Update handleDateChange to not auto-submit
function handleDateChange(newDate) {
    if (newDate && Array.isArray(newDate) && newDate.length === 2) {
        // Just update the date value, don't trigger search
        date.value = newDate;
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

// Add maxDate computed property for datepicker (1 year from now)
const maxDate = computed(() => {
   const oneYearFromNow = new Date();
   oneYearFromNow.setFullYear(oneYearFromNow.getFullYear() + 1);
   return oneYearFromNow;
});

// Update handleSearch
const handleSearch = () => {
    // Only require a location to be selected (don't check dates)
    if (!selectedPlace.value) return;
    
    // Update searchInput if we have a selected place
    if (selectedPlace.value) {
        searchInput.value = selectedPlace.value.name;
    }
    
    // Ensure lat/lng are properly typed as numbers
    const lat = selectedPlace.value?.lat ? parseFloat(selectedPlace.value.lat) : null;
    const lng = selectedPlace.value?.lng ? parseFloat(selectedPlace.value.lng) : null;
    
    const searchData = {
        location: {
            city: selectedPlace.value?.name || null,
            searchType: 'inPerson',
            live: false,
            lat: lat,
            lng: lng
        },
        dates: {
            // Always include explicit null values when no dates are selected
            start: null,
            end: null
        }
    };
    
    // Add dates if available
    if (date.value && Array.isArray(date.value) && date.value[0]) {
        const [start, end] = date.value;
        const formatForUrl = (date) => {
            return date.toISOString().split('T')[0] + ' 00:00:00';
        };
        
        searchData.dates = {
            start: formatForUrl(start),
            end: end ? formatForUrl(end) : formatForUrl(start)
        };
    }
    
    // Close dropdowns
    dateDropdown.value = false;
    dropdown.value = false;

    // Emit search event to parent with all data
    emit('search', searchData);
    emit('close-search');
};

// Update clearDates to emit to parent
const clearDates = () => {
    date.value = null;
    dateDropdown.value = false;
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