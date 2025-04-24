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
                <p class="font-bold">{{ searchInput }}</p>
            </div>
        </div>
        <div 
            v-else
            class="flex flex-col relative w-full border shadow-custom-6 rounded-4xl bg-white p-10 overflow-auto">
            <div class="w-full mt-2">
                <h2 style="font-family: 'Montserrat', sans-serif;" class="text-4.5xl text-black leading-8 font-bold">Where To?</h2>
                <button 
                    @click="cancelSearch"
                    class="absolute top-8 right-8 p-2 text-black"
                >
                    <svg class="w-8 h-8 fill-black">
                        <use xlink:href="/storage/website-files/icons.svg#ri-close-line"></use>
                    </svg>
                </button>
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
                        @click="clearSearchInput"
                        @keydown.backspace="handleBackspace"
                        autocomplete="off"
                        type="text">
                </div>
                
                <!-- Location Dropdown -->
                <ul 
                    class="bg-white w-full mx-0 overflow-hidden py-8 list-none" 
                    v-if="dropdown"
                    @click.stop>
                    <li 
                        class="text-2xl font-mediumm pb-2 flex items-center gap-8 hover:bg-neutral-100" 
                        v-for="place in places"
                        :key="place.place_id"
                        @click.stop="selectLocation(place)">
                        <div class="w-20 h-20 flex items-center justify-center bg-neutral-100 rounded-xl">
                            <svg class="w-14 h-14 transition-all duration-500 group-hover:fill-black group-hover:stroke-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s-8-5-8-11a8 8 0 1 1 16 0c0 6-8 11-8 11z"></path>
                                <circle cx="12" cy="11" r="3"></circle>
                            </svg>
                        </div>
                        {{place.description}}
                    </li>
                </ul>
            </div>
        </div>

        <!-- Dates Section -->
        <div 
            @click="showDatesSection"
            v-if="isVisible==='location' && showDatesTab" 
            class="relative flex w-full border shadow-custom-3 rounded-4xl bg-white p-8 justify-between">
            <div>
                <p>When</p>
            </div>
            <div>
                <p class="font-bold">{{ date ? formatDateRange : 'Add Dates' }}</p>
            </div>
        </div>
        <div 
            v-if="isVisible==='dates' && showDatesTab"
            class="flex-grow relative w-full border shadow-custom-6 rounded-4xl bg-white p-10 overflow-auto">
            <div class="w-full mt-2">
                <h2 style="font-family: 'Montserrat', sans-serif;" class="text-4.5xl text-black leading-8 font-bold">When?</h2>
            </div>
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
                :config="{ noSwipe: true }"
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

        <div class="w-full bg-white p-8 flex justify-between items-center mt-auto">
            <div>
                <button @click="handleClearAll" class="underline text-4xl font-medium">Clear All</button>
            </div>
            <div>
                <button 
                    @click="handleSearch"
                    :disabled="props.showDatesTab ? (!selectedPlace && (!date || !date.length)) : !selectedPlace"
                    :class="[
                        'py-4 px-8 rounded-2xl flex gap-4 text-4xl font-medium flex items-center',
                        (selectedPlace || (props.showDatesTab && date && date.length)) ? 'bg-[#ff385c] text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                    ]">
                    Search
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
    initialEndDate: String,
    search: {
        type: String,
        default: null
    },
    showDatesTab: {
        type: Boolean,
        default: true
    }
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

// Update emits definition
const emit = defineEmits(['update:location', 'update:modelValue', 'clear']);

// Initialize dark mode and timezone variables for datepicker
const isDark = ref(false);
const tz = computed(() => {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
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
    if (!autoComplete) {
        console.error('Autocomplete service not available');
        return;
    }
    
    if (searchInput.value) {
        try {
            const response = await autoComplete.getPlacePredictions({ 
                input: searchInput.value, 
                types: ['(cities)'] 
            });
            places.value = response?.predictions || initializePlaces();
        } catch (error) {
            console.error('Error fetching place predictions:', error);
            places.value = initializePlaces();
        }
    } else {
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
       // Fallback to old method if the new one fails
       service.getDetails({ placeId: location.place_id }, data => {
           setPlace(data);
       });
   }
};

const setPlace = (place) => {
   // The new Place API uses camelCase and different property structure
   // Extract lat/lng correctly based on whether they're functions or properties
   let lat = null;
   let lng = null;
   let name = null;
   
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
   
   // Get name from appropriate property
   if (place.displayName && place.displayName.text) {
       name = place.displayName.text;
   } else if (place.formattedAddress) {
       name = place.formattedAddress;
   } else if (place.name) {
       name = place.name;
   } else {
       name = "Unknown location";
   }
   
   // Clean up the name to remove zip codes
   // This regex matches a comma followed by a space and then 5 digits (US zip code)
   // or matches comma, space, letter(s), space, and then postal code format (international)
   name = name.replace(/,\s+\d{5}(-\d{4})?($|,)/g, '');
   
   // Also remove state/province and country to just keep city
   const nameParts = name.split(',');
   if (nameParts.length > 1) {
       // Just keep the first part (city name)
       name = nameParts[0].trim();
   }
   
   selectedPlace.value = {
       name: name,
       lat: lat,
       lng: lng
   };
   
   searchInput.value = selectedPlace.value.name;
   dropdown.value = false;
   
   // Get formatted dates using the global formatDateForUrl function
   const formattedStartDate = date.value && date.value[0] ? formatDateForUrl(date.value[0]) : null;
   const formattedEndDate = date.value && date.value[1] ? formatDateForUrl(date.value[1]) : formattedStartDate;
   
   // Update parent component
   emit('update:location', {
       city: selectedPlace.value.name, 
       lat: lat,
       lng: lng,
       start: formattedStartDate,
       end: formattedEndDate
   });
   
   // Immediately trigger search like desktop version instead of switching to dates
   handleSearch();
};

const initGoogleMaps = async () => {
    try {
        if (!window.google || !window.google.maps) {
            console.error('Google Maps API not loaded');
            return;
        }

        // Use the new async importLibrary approach
        const { AutocompleteService, PlacesService } = await google.maps.importLibrary("places");
        autoComplete = new AutocompleteService();
        
        // Still need PlacesService for fallback compatibility
        service = new PlacesService(document.getElementById("places"));
        
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
    
    // Show initial places immediately
    places.value = initializePlaces();
    dropdown.value = true;

    // Initialize Google Maps with async loading and new approach
    if (!window.google || !window.google.maps) {
        let script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=AIzaSyBxpUKfSJMC4_3xwLU73AmH-jszjexoriw&libraries=places&callback=initMap&loading=async`;
        script.async = true;
        script.defer = true;
        
        window.initMap = async () => {
            if (!window.googleMapsInitialized) {
                await initGoogleMaps();
                window.googleMapsInitialized = true;
            }
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

    // Remove search trigger listener
    window.removeEventListener('trigger-search', handleSearch);
});

// Use computed properties for date logic
const propsDateRange = computed(() => {
  if (props.initialStartDate && props.initialEndDate) {
    try {
      const startDate = new Date(props.initialStartDate);
      const endDate = new Date(props.initialEndDate);
      
      if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
        return [startDate, endDate];
      }
    } catch (e) {
      console.error('Error parsing dates from props:', e);
    }
  }
  return null;
});

// When you need to access dates
const effectiveDate = computed(() => {
  if (date.value) return date.value;
  if (propsDateRange.value) return propsDateRange.value;
  return null;
});

// Format dates for display
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

// Add a reusable function for formatting dates for consistency
const formatDateForUrl = (dateObj) => {
    if (!dateObj) return null;
    return dateObj.toISOString().split('T')[0] + ' 00:00:00';
};

// Update handleSearch function
const handleSearch = () => {
    if (!selectedPlace.value && !date.value) return;
    
    // Hide the search modal
    window.dispatchEvent(new CustomEvent('hide-search'));
    
    // Format dates if available
    const formattedStartDate = date.value && date.value[0] ? formatDateForUrl(date.value[0]) : null;
    const formattedEndDate = date.value && date.value[1] ? formatDateForUrl(date.value[1]) : formattedStartDate;
    
    // Build search URL parameters
    const params = new URLSearchParams();
    
    // Add location parameters if we have a city
    if (selectedPlace.value) {
        params.set('city', selectedPlace.value.name);
        
        if (selectedPlace.value.lat !== null && selectedPlace.value.lng !== null) {
            params.set('lat', selectedPlace.value.lat);
            params.set('lng', selectedPlace.value.lng);
        }
        
        params.set('searchType', 'inPerson');
        params.set('live', 'false');
    } else {
        // For date-only search, use 'null' searchType
        params.set('searchType', 'null');
    }
    
    // Add date parameters if they exist
    if (formattedStartDate) {
        params.set('start', formattedStartDate);
        params.set('end', formattedEndDate || formattedStartDate);
    }
    
    // Update parent component with complete location and date data
    emit('update:location', {
        city: selectedPlace.value?.name || null, 
        lat: selectedPlace.value?.lat || null,
        lng: selectedPlace.value?.lng || null,
        start: formattedStartDate,
        end: formattedEndDate
    });
    
    // Navigate directly to the search page
    window.location.href = `/index/search?${params.toString()}`;
};

// Update handleDateChange to use the new formatDateForUrl function
function handleDateChange(newDate) {
   if (newDate && Array.isArray(newDate) && newDate.length === 2) {
       dateDropdown.value = false;
       
       const formattedStartDate = formatDateForUrl(newDate[0]);
       const formattedEndDate = formatDateForUrl(newDate[1]) || formattedStartDate;
       
       date.value = newDate;
       
       // Update parent component if we have location data
       if (selectedPlace.value) {
           emit('update:location', {
               city: selectedPlace.value.name,
               lat: selectedPlace.value.lat,
               lng: selectedPlace.value.lng,
               start: formattedStartDate,
               end: formattedEndDate
           });
       } else {
           // Also emit update for date-only search
           emit('update:location', {
               city: null,
               lat: null,
               lng: null,
               start: formattedStartDate,
               end: formattedEndDate
           });
           
           // Encourage the user to search after selecting dates
           // This shows a clear CTA to proceed with a date-only search
           setTimeout(() => {
               // Keep the dates section visible so user can see their selection
               isVisible.value = 'dates';
           }, 100);
       }
   }
}

// Add this computed property
const minDate = computed(() => {
   const today = new Date();
   today.setHours(0, 0, 0, 0); // Set to start of day
   return today;
});

// Update clearDates to properly emit location updates
const clearDates = () => {
   date.value = null;
   dateDropdown.value = false;
   
   // Emit update with cleared dates but preserve location
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
    
    // Clear the input field but preserve the selectedPlace
    // This allows for new searches while keeping the previous selection
    searchInput.value = '';
    dropdown.value = true;
    places.value = initializePlaces();
};

const showDatesSection = () => {
    // Only switch to dates section if showDatesTab is true
    if (!props.showDatesTab) {
        // If dates tab is disabled, just trigger the search function directly
        handleSearch();
        return;
    }
    
    // Switch to dates section regardless of whether a place was selected
    isVisible.value = 'dates';
    dropdown.value = false;
    
    // If a place is selected, make sure the search input shows the selected place name
    if (selectedPlace.value) {
        searchInput.value = selectedPlace.value.name;
    }
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
        
        // Emit update with all values cleared
        emit('update:location', {
            city: null,
            lat: null,
            lng: null,
            start: null,
            end: null
        });
    }
    
    isVisible.value = 'location';
    dropdown.value = true;
    places.value = initializePlaces();
};

// Add the handleClearAll method
const handleClearAll = () => {
    // Clear everything
    searchInput.value = '';
    selectedPlace.value = null;
    date.value = null;
    
    // Emit update with all values cleared
    emit('update:location', {
        city: null,
        lat: null,
        lng: null,
        start: null,
        end: null
    });
    
    // Reset the UI state
    isVisible.value = 'location';
    dropdown.value = true;
    places.value = initializePlaces();
    
    // Also emit clear event for parent components
    emit('clear');
};

// Expose the methods to the parent
defineExpose({ clearState, handleClearAll, showDatesSection });

// Add this new method after the searchInput declaration
const clearSearchInput = () => {
    // Clear the input but preserve the selectedPlace data
    // This is for when the user clicks the input to start a new search
    searchInput.value = '';
    dropdown.value = true;
    places.value = initializePlaces();
};

// Update cancel search method
const cancelSearch = () => {
    // If we have a selected place, make sure the input shows it
    if (selectedPlace.value) {
        searchInput.value = selectedPlace.value.name;
    }
    
    // Just hide the search without clearing anything
    window.dispatchEvent(new CustomEvent('hide-search'));
};

// Add a method to handle backspace key
const handleBackspace = (event) => {
    // If this is the first backspace press when the input is focused,
    // clear the entire field to allow for a fresh search
    if (event.target.selectionStart === event.target.value.length && 
        searchInput.value === selectedPlace.value?.name) {
        searchInput.value = '';
        dropdown.value = true;
        places.value = initializePlaces();
        
        // Prevent the default backspace behavior since we've cleared the field
        event.preventDefault();
    }
};
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

/* Calendar cells - make them square with aspect ratio like in dates.vue */
.dp__calendar_item {
   margin: 0 !important;
   padding: 0 !important;
   font-size: 1.4rem !important;
   display: flex !important;
   justify-content: center !important;
   position: relative !important;
   width: calc(100% / 7) !important; /* Ensure 7 equal columns */
}

/* Create square aspect ratio */
.dp__calendar_item::before {
   content: '';
   display: block;
   padding-top: 100%; /* Creates 1:1 aspect ratio */
}

/* Position the content absolutely within the square */
.dp__calendar_item > * {
   position: absolute !important;
   top: 0 !important;
   left: 0 !important;
   right: 0 !important;
   bottom: 0 !important;
   display: flex !important;
   align-items: center !important;
   justify-content: center !important;
}

/* Update cell_inner to work with new approach */
.dp__cell_inner {
   position: absolute !important;
   height: 100% !important;
   width: 100% !important;
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
