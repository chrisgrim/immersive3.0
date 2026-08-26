<template>
    <div class="location-search-pill relative flex w-full border border-slate-300 rounded-full"
    :class="[{ 'bg-neutral-200': dropdown || dateDropdown || (showFilters && !compact) }, { 'is-compact': compact }]"
    @mousedown.capture="interceptMousedown"
    @click.capture="interceptClick">
       <!-- Location Search -->
       <div
           class="flex-1 mr-2 transition-opacity duration-200"
           :class="{ 'opacity-50': dateDropdown || (showFilters && !compact) }"
           v-click-outside="closeLocationDropdown"
       >
           <svg class="location-search-icon absolute top-1/2 -translate-y-1/2 left-8 w-8 h-8 fill-black z-[1002]">
               <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
           </svg>
           <input
               ref="loc"
               class="location-search-input relative text-1xl rounded-full h-full bg-transparent w-full font-bold z-40 focus:border-none focus:rounded-full focus:bg-white focus:shadow-custom-7 placeholder-black"
               v-model="searchInput"
               :placeholder="compact ? 'Search' : 'Search by City'"
               @input="updateLocations"
               @focus="onInputFocus"
               autocomplete="false"
               type="text">

           <!-- Location Dropdown -->
           <ul
               class="absolute bg-white w-full mx-0 overflow-hidden mt-8 p-8 list-none rounded-5xl shadow-custom-7"
               v-if="dropdown"
               @click.stop>
               <!-- Recent Searches sit above the default cities (not in
                    place of them) when the user is logged in and has at
                    least one saved search — gone once they start typing,
                    when the list below switches to live predictions. Each
                    group gets its own label (only at rest — see
                    showRecentSearches/showSuggestedHeader) so the two kinds
                    of rows read as distinct sections, not one blended list. -->
               <li v-if="showRecentSearches" class="px-8 pt-4 pb-2 text-sm font-semibold text-neutral-500">
                   Recent searches
               </li>
               <RecentSearchesList v-if="showRecentSearches" :searches="recentSearches" />
               <li v-if="showSuggestedHeader" class="px-8 pt-4 pb-2 text-sm font-semibold text-neutral-500" :class="{ 'border-t border-neutral-100 mt-4': showRecentSearches }">
                   Suggested destinations
               </li>
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

       <!-- Date + Search share one background group, instead of the date
            button's own white pill visually ending before the search button
            — when active it should look like one continuous shape (see
            reference), not two pills with a gap. A shared wrapper background
            achieves that without moving either button (moving the Search
            button over the date button via negative margin was tried first
            and broke clicking Dates — its hitbox is a rectangle regardless
            of the rounded corners, so it silently ate the overlapped area). -->
       <!-- No items-center here — default stretch keeps the Search button's
            original full-height sizing (it only had `my-2` for its inset,
            not its own height); items-center previously squashed it down to
            its own intrinsic content height instead. -->
       <div class="flex rounded-full transition-all duration-200" :class="[{ 'bg-white shadow-custom-7': dateDropdown || (dateHover && !compact) || showDatesNudge }, { 'opacity-50': showFilters && !compact }]">
           <div v-click-outside="closeDateDropdown">
               <button
                   @click.stop="handleDateClick"
                   @mouseenter="dateHover = true"
                   @mouseleave="dateHover = false"
                   class="text-1xl rounded-full font-bold flex items-center gap-2 location-search-date-btn"
               >
                   <span v-if="date">{{ formatDateRange }}</span>
                   <span v-else>Dates</span>
               </button>

               <!-- Date Picker Dropdown -->
               <div
                   v-if="dateDropdown"
                   class="absolute -left-0 w-full mt-8 bg-white rounded-5xl shadow-custom-7 p-8 z-40 location-search-calendar"
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
               class="location-search-submit rounded-full my-2 mr-2 font-semibold transition-colors flex items-center justify-center"
               :class="[
                   isOnSearchPage || selectedPlace || date ?
                   ['bg-default-red text-white cursor-pointer', { 'hover:bg-red-600': !compact }] :
                   'bg-black text-white cursor-not-allowed opacity-50'
               ]"
           >
               <svg class="location-search-submit-icon flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" width="16" height="16" aria-hidden="true">
                   <circle cx="11" cy="11" r="7"></circle>
                   <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
               </svg>
               <span class="location-search-submit-label">Search</span>
           </button>
       </div>

       <SearchFilterButton
           :has-active-filters="hasActiveFilters"
           :compact="compact"
           :active="showFilters && !compact"
           :vertical-inset="0.5"
           @open="openFilters"
       />

       <!-- Same positioning language as the location suggestions/calendar
            dropdowns above — nested inside this pill (which is already
            `position: relative`) so `width: 100%` matches the pill's own
            real rendered width for free, rather than trying to duplicate
            it via a measurement or a hardcoded value elsewhere. -->
       <Filters
           v-if="showFilters"
           :model-value="filtersState"
           :show-price="isOnSearchPage"
           @close="$emit('close-filters')"
           @filter-change="$emit('filters-changed', $event)"
       />
   </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useRecentSearches } from '@/composables/useRecentSearches';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import { importMapsLibrary } from '@/composables/useGoogleMaps';
import axios from 'axios';
import Filters from './filters.vue';
import SearchStore from '@/Stores/SearchStore.vue';
import SearchFilterButton from './search-filter-button.vue';
import RecentSearchesList from './recent-searches-list.vue';
import { saveSearch } from '@/composables/useSavedSearches';

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
    },
    // Drives the morph into the scrolled-collapsed pill — the same elements
    // shrink in place (max-width, padding, the Search label's own width) via
    // CSS transitions, rather than swapping in a differently-sized replica.
    compact: {
        type: Boolean,
        default: false
    },
    hasActiveFilters: {
        type: Boolean,
        default: false
    },
    showFilters: {
        type: Boolean,
        default: false
    }
});

const filtersState = computed(() => SearchStore.state.filters);

// Add this to detect production mode
const isProduction = import.meta.env.PROD;

// Existing date-related refs
const dateDropdown = ref(false);
// Drives the shared Date+Search group's shadow on hover — previously the
// hover shadow lived on just the Dates button itself, so hovering showed a
// small button-sized shadow instead of matching the merged pill the active
// (dateDropdown-open) state shows.
const dateHover = ref(false);
const dropdown = ref(false);
const searchInput = ref('');
const places = ref(initializePlaces());
// Programmatic focus target for interceptMousedown — see its own comment.
const loc = ref(null);
const date = ref(null);
const isDark = ref(false); // For date picker theme

// Recent Searches — takes over the dropdown's default-list state in place
// of the hardcoded default cities above (see the template's
// showRecentSearches branch). Fetched once, only for logged-in users; stays
// empty (falling back to the default list) for guests or a failed fetch.
// Same endpoint the Hub's full Saved Search Preferences page calls (which
// intentionally shows everything, up to the 50-search cap) — this dropdown
// is a quick-access shortcut, not a management view, so the WHOLE resting
// list (Recent Searches + the default cities below it) is capped to a
// short combined total, not just the recent-searches half on top of a
// separately-uncapped default list. The backend already orders pinned
// first, then most-recently-searched, so slicing the top DROPDOWN_TOTAL_LIMIT
// naturally means "all your pins (up to the cap), topped up with your most
// recent unpinned searches" — no separate query needed. Default cities then
// fill whatever's left (see defaultPlacesList()) instead of being appended
// in full regardless of how many recent searches already show.
// Declared above the composable calls below, which read it. Vue's SFC
// compiler happens to hoist a static literal like this one, so the old
// ordering worked — but only for as long as the value stays a literal.
// Written out here so nothing depends on that optimisation.
const DROPDOWN_TOTAL_LIMIT = 6;

// Shared with the other three nav search panels — see
// composables/useRecentSearches.js for why this one piece is common
// while the panels themselves stay separate.
const { recentSearches, fetchRecentSearches } = useRecentSearches({
    limit: DROPDOWN_TOTAL_LIMIT,
    once: true,
    // Give back the dropdown slots the recent searches just claimed —
    // but never stomp a query the user has since typed.
    onLoaded: () => {
        if (!hasTypedSinceFocus.value) {
            places.value = defaultPlacesList();
        }
    },
});

// The default city list fills whatever's left of the shared combined cap
// (see DROPDOWN_TOTAL_LIMIT below) after Recent Searches takes its share —
// e.g. 3 pinned searches leaves room for 3 default cities, not the full
// hardcoded list appended on top for a combined 8-9 rows. Only applies to
// the at-rest view; live Google predictions while typing aren't capped
// against Recent Searches since that list is hidden then anyway (see
// showRecentSearches).
const defaultPlacesList = () => initializePlaces().slice(0, Math.max(0, DROPDOWN_TOTAL_LIMIT - recentSearches.value.length));

// Keyed on "typed since focus", not "input is empty" — reopening the pill
// on a results page focuses an input already showing the committed city
// (e.g. "New York"), which isn't the user having typed anything new. See
// onInputFocus (resets this) and updateLocations (sets it on a keystroke).
const hasTypedSinceFocus = ref(false);
const showRecentSearches = computed(() => !hasTypedSinceFocus.value && recentSearches.value.length > 0);
// The default city list gets its own "Suggested destinations" header only
// at rest — while actively typing, `places` holds live predictions instead
// (see updateLocations), which aren't "suggestions" and don't get a header.
const showSuggestedHeader = computed(() => !hasTypedSinceFocus.value && places.value.length > 0);

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
const emit = defineEmits(['location-updated', 'search', 'close-search', 'dates-cleared', 'expand-requested', 'open-filters', 'close-filters', 'filters-changed']);

// While compact, an open dropdown would be left anchored to a pill that's
// about to shrink out from under it (e.g. scrolling away mid-interaction) —
// close both so nothing is left floating against the wrong-sized pill.
//
// Snapshot of the last actually-committed search (hydrated from the URL/
// props at mount, or updated in handleSearch() right before it applies).
// If the user picks a city or date range but scrolls away without
// pressing Search, that pick was never committed — collapsing shouldn't
// leave it showing in the compact pill as if it were the active search.
const committedSearchInput = ref('');
const committedSelectedPlace = ref(null);
const committedDate = ref(null);

// After picking a city, the bar otherwise goes blank with no cue toward
// what to do next — nudge the user toward Dates by giving it the same
// highlighted look it gets on hover/open, for as long as the pick is
// uncommitted (not yet applied via Search) and no dates are set yet. Once
// dates are picked, or the search is actually submitted (committing
// selectedPlace — see handleSearch), the nudge resolves on its own.
const showDatesNudge = computed(() => (
    !props.compact &&
    !!selectedPlace.value &&
    selectedPlace.value !== committedSelectedPlace.value &&
    !date.value
));

watch(() => props.compact, (isCompact) => {
    if (isCompact) {
        // The input stays mounted (v-show, not v-if) across collapse, so if
        // it was focused when the pill shrank, it silently KEEPS that focus
        // — nothing else here ever blurs it. The dropdown only ever reopens
        // via the input's 'focus' event (see onInputFocus), which the
        // browser won't refire for an element that's already focused. Left
        // unblurred, scrolling back to the top (auto re-expanding without a
        // click — see nav-search.vue's handleScroll) leaves the input
        // showing a cursor but the dropdown closed, and clicking it again
        // does nothing: no new focus event, so onInputFocus never reruns.
        loc.value?.blur();
        dateDropdown.value = false;
        dropdown.value = false;
        if (searchInput.value !== committedSearchInput.value) {
            searchInput.value = committedSearchInput.value;
            selectedPlace.value = committedSelectedPlace.value;
        }
        if (date.value !== committedDate.value) {
            date.value = committedDate.value;
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

// Each keystroke fires its own independent, undebounced Places API call —
// rapid typing (or rapid backspacing) can have an OLDER request's response
// arrive after a NEWER one, silently overwriting the correct current-input
// results with stale ones for whatever was typed several keystrokes ago.
// Bumped at the start of every call and checked before each async result is
// applied, so only the most recently fired request can ever win.
let updateLocationsToken = 0;

const updateLocations = async () => {
   const token = ++updateLocationsToken;

   // Always stays open while typing — there's always something to show
   // (predictions, or the fallback below), so this must never flip to
   // false here. It previously tracked searchInput's length, which closed
   // the whole dropdown the moment a backspace cleared the field back to
   // empty instead of falling back to the default list.
   dropdown.value = true;

   if (!searchInput.value) {
       // Cleared back to empty (e.g. backspaced everything) — same view as
       // a fresh focus: Recent Searches first, falling back to the default
       // city list below, not a stuck "typed" state.
       hasTypedSinceFocus.value = false;
       places.value = defaultPlacesList();
       return;
   }

   // Any keystroke means the user is actively searching, not just reopening
   // the pill — switch away from Recent Searches to live predictions (see
   // hasTypedSinceFocus's declaration for why this can't just check whether
   // searchInput is empty).
   hasTypedSinceFocus.value = true;

   try {
       if (autoComplete) {
           const { predictions } = await autoComplete.getPlacePredictions({
               input: searchInput.value,
               types: ['(cities)']
           });
           if (token !== updateLocationsToken) return;
           places.value = predictions || initializePlaces();
       }
   } catch (error) {
       if (token !== updateLocationsToken) return;
       console.error('Error fetching place predictions:', error);
       places.value = initializePlaces();
   }
};

const selectLocation = async (location) => {
   // Close the dropdown and reflect the pick immediately — waiting on the
   // network round-trip below (fetching precise lat/lng from Google) left
   // the dropdown open and the Search button greyed out for as long as
   // that request took. setPlace() below refines this with the exact
   // coordinates once the fetch resolves.
   dropdown.value = false;
   const cityParts = location.description.split(',');
   searchInput.value = cityParts[0].trim();
   selectedPlace.value = { name: location.description, lat: null, lng: null };
   // Auto-advance to Dates, the natural next step — also gives a clear
   // signal the pick registered, instead of the pill just quietly updating
   // its text with no other feedback.
   dateDropdown.value = true;

   try {
       const { Place } = await importMapsLibrary("places");
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

   // Store the formatted place name
   selectedPlace.value = {
       name: placeName,
       lat: lat,
       lng: lng
   };

   // Show only the city name in the search input for better UX
   // But keep the full city, state, zipcode format for API calls
   const cityParts = placeName.split(',');
   searchInput.value = cityParts[0].trim();

   dropdown.value = false;
};

const initGoogleMaps = async () => {
   try {
       // Shared bootstrap loader — guarantees importLibrary exists.
       const { AutocompleteService } = await importMapsLibrary("places");
       autoComplete = new AutocompleteService();
   } catch (error) {
       console.error('Error initializing Google Maps Places API:', error);
   }
};


// Lazy, not onMounted — this component stays mounted (v-show, not v-if)
// across every page load site-wide (see nav-search.vue), so fetching here
// on mount cost every logged-in visitor an extra request+query on every
// page, not just the ones where the dropdown ever opens. Fetched once, the
// first time the dropdown actually opens (see onInputFocus).

// Initialize from URL parameters
onMounted(() => {
    // Initialize city from URL or props
    const params = new URLSearchParams(window.location.search);
    if (params.get('city')) {
        // Store the full city name for search data
        const fullCityName = params.get('city');
        selectedPlace.value = {
            name: fullCityName,
            lat: parseFloat(params.get('lat')),
            lng: parseFloat(params.get('lng'))
        };

        // Display only the city part in the search input
        const cityParts = fullCityName.split(',');
        searchInput.value = cityParts[0].trim();
    } else if (props.initialCity) {
        const cityParts = props.initialCity.split(',');
        searchInput.value = cityParts[0].trim();
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

    // This IS the committed state at this point — either hydrated from the
    // URL/props (an already-applied search) or still empty (nothing applied
    // yet). Anything the user picks after this is provisional until
    // handleSearch() re-snapshots it.
    committedSearchInput.value = searchInput.value;
    committedSelectedPlace.value = selectedPlace.value;
    committedDate.value = date.value;

    // Initialize Google Maps via the shared bootstrap loader (loads once,
    // guarantees google.maps.importLibrary — no manual <script>/initMap needed).
    initGoogleMaps();

    // Add a window event listener for popstate to update UI when URL changes.
    // Named and held in a local, not the inline arrow this used to be — an
    // anonymous listener can't be removed, so every mount of the desktop
    // search bar left another copy bound to window's 'popstate', each still
    // writing searchInput on a component that had already unmounted. Removed
    // alongside the observer below.
    const handlePopState = () => {
        const params = new URLSearchParams(window.location.search);
        if (params.has('city')) {
            const fullCityName = params.get('city');
            const cityParts = fullCityName.split(',');
            searchInput.value = cityParts[0].trim();
        }
    };
    window.addEventListener('popstate', handlePopState);

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
        window.removeEventListener('popstate', handlePopState);
    });
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

// Add the isPlaceEmpty function for consistency with mobile version
const isPlaceEmpty = (place) => {
    if (!place) return true;
    return !place.name && !place.lat && !place.lng;
};

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
       // Filters is parent-owned state (the showFilters prop), not a local
       // ref like dropdown/dateDropdown above — closing it needs an emit
       // rather than just an assignment here. Without this, opening Dates
       // while Filters was already open left both panels rendered at once,
       // visually overlapping (Filters is positioned over the same area).
       emit('close-filters');

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

// The compact case never reaches here — interceptClick (below) stops the
// click before it gets to this button at all while the pill is collapsed.
function handleDateClick() {
    toggleDateDropdown();
}

function closeLocationDropdown() {
   dropdown.value = false;
}

// Mirrors toggleDateDropdown's own close-the-others behavior — opening
// Filters while Dates or the location suggestions were open otherwise left
// them rendered underneath it, visually overlapping (see toggleDateDropdown's
// comment for the same bug in the other direction).
function openFilters() {
    dateDropdown.value = false;
    dropdown.value = false;
    emit('open-filters');
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

// Add this computed property to check if we're on a search page
const isOnSearchPage = computed(() => {
    return window.location.pathname.includes('/search');
});

// Update handleSearch
const handleSearch = async () => {
    // Check if we're on a search page
    const isSearchPage = window.location.pathname.includes('/search');

    // Build search data object
    const searchData = {
        location: {
            city: selectedPlace.value?.name || null,
            live: false,
            lat: selectedPlace.value?.lat ? parseFloat(selectedPlace.value.lat) : null,
            lng: selectedPlace.value?.lng ? parseFloat(selectedPlace.value.lng) : null
        },
        dates: {
            // Always include explicit null values when no dates are selected
            start: null,
            end: null
        }
    };

    // Add dates if available
    if (date.value && Array.isArray(date.value) && date.value.length === 2) {
        const [start, end] = date.value;

        // Format dates in UTC to avoid timezone issues
        const formatForUrl = (date) => {
            // Create a new date set to midnight UTC on the selected date
            const d = new Date(Date.UTC(
                date.getFullYear(),
                date.getMonth(),
                date.getDate(),
                0, 0, 0
            ));
            return d.toISOString().split('T')[0] + ' 00:00:00';
        };

        searchData.dates = {
            start: formatForUrl(start),
            end: end ? formatForUrl(end) : formatForUrl(start)
        };
    }

    // Close dropdowns
    dateDropdown.value = false;
    dropdown.value = false;

    // Determine if we should emit or redirect based on conditions
    const hasDates = searchData.dates.start !== null;
    const hasLocation = selectedPlace.value !== null;


    // Enable search only if we have dates OR location OR we're already on a search page
    if (!hasDates && !hasLocation && !isSearchPage) {
        return;
    }

    // Auto-save this as the user's "recent search" — lives here (not in
    // nav-search.vue's handleLocationSearch) because a search from OUTSIDE
    // a search page (e.g. the homepage — the most common first-ever search)
    // redirects directly below without ever emitting 'search' to the
    // parent; putting the save here covers both paths from one place. See
    // useSavedSearches.js for the guest-skip/fire-and-forget reasoning.
    if (hasLocation || hasDates) {
        await saveSearch(searchData.location.city || 'All Locations', {
            city: searchData.location.city,
            lat: searchData.location.lat,
            lng: searchData.location.lng,
            searchType: 'inPerson',
            live: searchData.location.live,
            start: searchData.dates.start,
            end: searchData.dates.end,
            categories: filtersState.value.categories,
            tags: filtersState.value.tags,
            price: filtersState.value.price
        });
    }

    // If on a search page, emit the search event
    if (isSearchPage) {
        // This search is now actually applied — re-snapshot so a later
        // scroll-collapse (with nothing new picked since) doesn't discard it.
        committedSearchInput.value = searchInput.value;
        committedSelectedPlace.value = selectedPlace.value;
        committedDate.value = date.value;
        emit('search', searchData);
        emit('close-search');
        return;
    }

    // If not on a search page, redirect to search page with params
    const params = new URLSearchParams();

    // Add location params if we have a location
    if (hasLocation) {
        params.set('city', searchData.location.city);

        if (searchData.location.lat !== null) {
            params.set('lat', searchData.location.lat.toString());
        }

        if (searchData.location.lng !== null) {
            params.set('lng', searchData.location.lng.toString());
        }

        params.set('searchType', 'inPerson');
        params.set('live', 'false');
    } else if (hasDates) {
        // For date-only search
        params.set('searchType', 'null');
    }

    // Add date params if we have dates
    if (hasDates) {
        params.set('start', searchData.dates.start);
        params.set('end', searchData.dates.end || searchData.dates.start);
    }

    // Redirect to search page
    window.location.href = `/index/search?${params.toString()}`;
};

// Update clearDates to not immediately search
const clearDates = () => {
    // Clear the local state
    date.value = null;

    // Keep the calendar dropdown open - don't close it
    // dateDropdown.value = false; <-- Remove this line

    // We don't emit dates-cleared here anymore - user needs to click Search button
    // to confirm the change
};

// Nothing inside the collapsed pill should be independently interactive —
// it's a summary, not a working mini search bar. A single click anywhere on
// it (city input, Dates, Search, the filter icon) should just expand to the
// real thing, not open a dropdown or run a stale search from whatever was
// last typed. Intercepted at the capture phase, on both mousedown and click,
// so nothing downstream needs its own compact-awareness.
//
// mousedown's job is narrower than it looks: block the browser's native
// focus-on-mousedown before it happens (`click` alone is too late for that).
// Left unblocked, a mousedown landing directly on the <input> would focus it
// natively, which fires onInputFocus synchronously — emitting
// expand-requested and shifting the layout (the tabs row appears above the
// pill) before the browser's separate 'click' event fires. Since that DOM
// event's propagation path is computed once at dispatch start, the click
// would then land on the newly-revealed tabs row instead of the pill,
// escaping this interceptor entirely and reaching v-click-outside, which
// immediately closes the dropdown this same interaction just opened.
// preventDefault on mousedown heads that off at the source; the actual
// expand+route below happens from click instead, once THIS click has
// already been fully dispatched, so nothing later can retroactively steal
// where it lands.
//
// Both handlers read the live `compact` prop directly — nothing needs
// capturing across the gesture. That in turn means a keyboard-triggered
// click (Enter/Space on the Dates or filter button while compact, which
// never fires a mousedown to begin with) is handled the same way as a real
// mouse click: compact is still true when click fires either way, since
// mousedown's preventDefault is what keeps it from flipping mid-gesture.
function interceptMousedown(event) {
    if (!props.compact) return;
    event.preventDefault();
    event.stopPropagation();
}

function interceptClick(event) {
    if (!props.compact) return;
    event.preventDefault();
    event.stopPropagation();

    // Routed by region rather than always opening the location search —
    // Dates and Filters are real, independently clickable targets again
    // (see the removed pointer-events: none in <style>), so clicking
    // either one directly should open THAT, not the city search. Anything
    // else (the pill's own background/padding) falls back to search.
    if (event.target.closest('.location-search-date-btn')) {
        emit('expand-requested');
        toggleDateDropdown();
    } else if (event.target.closest('.search-filter-button')) {
        emit('expand-requested');
        emit('open-filters');
    } else {
        loc.value?.focus();
    }
}

function onInputFocus(event) {
    if (props.compact) {
        emit('expand-requested');
    }

    dropdown.value = true;
    // Same close-the-others reasoning as toggleDateDropdown/openFilters —
    // focusing the location input while either was open otherwise left it
    // rendered underneath this dropdown.
    dateDropdown.value = false;
    emit('close-filters');
    // Reopening the pill on a results page focuses an input already
    // showing the committed city (e.g. "New York") — that's not the user
    // having typed anything, so Recent Searches should still show; only an
    // actual keystroke (see updateLocations) should switch it away.
    hasTypedSinceFocus.value = false;

    // Select (not clear) any existing text — clearing outright wiped an
    // already-committed, correct value (e.g. simply clicking to expand a
    // pill that already shows "New York") even when nothing was meant to
    // change. Selecting still makes it trivial to overwrite by typing, but
    // leaves the current value visible/intact otherwise.
    event.target.select();

    // Default locations — shown whenever Recent Searches doesn't apply
    // (see showRecentSearches).
    places.value = defaultPlacesList();

    fetchRecentSearches();
}
</script>

<style>
/* Add a scoping class to all styles and make them !important */
.location-search-calendar .dp__menu_inner .dp__menu_items {
   display: none !important;
}

/* Calendar styling */
.location-search-calendar .dp__calendar {
   width: 100% !important;
}

/* Header month/year styling */
.location-search-calendar .dp__month_year_wrap {
   font-size: 1.7rem !important;
   font-weight: 400 !important;
}

/* Calendar header (days of week) */
.location-search-calendar .dp__calendar_header {
   color: #666 !important;
   font-weight: normal !important;
   margin-bottom: 8px !important;
   font-size: 1.2rem !important;
}

.location-search-calendar .dp__calendar_row {
   margin: 0 !important;
   gap: 0 !important;
}

.location-search-calendar .dp__calendar_item {
   margin: 0 !important;
   padding: 0 !important;
   font-size: 1.4rem !important;
}

/* Calendar cells */
.location-search-calendar .dp__cell_inner {
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

.location-search-calendar .dp__cell_disabled {
   opacity: 0.3 !important;
   cursor: auto !important;
}

/* Hover state */
.location-search-calendar .dp__cell_inner:not(.dp--past):hover {
   border: 2px solid black !important;
   background: transparent !important;
   color: black !important;
}

/* Selected state */
.location-search-calendar .dp__active {
   background-color: black !important;
   color: white !important;
}

/* Range styling */
.location-search-calendar .dp__range_start,
.location-search-calendar .dp__range_end {
   background-color: black !important;
   color: white !important;
}

.location-search-calendar .dp__range_start {
   border-top-right-radius: 0 !important;
   border-bottom-right-radius: 0 !important;
}

.location-search-calendar .dp__range_end {
   border-top-left-radius: 0 !important;
   border-bottom-left-radius: 0 !important;
}

.location-search-calendar .dp__range_between {
   border-radius: 0 !important;
}

/* Navigation arrows */
.location-search-calendar .dp__arrow_bottom,
.location-search-calendar .dp__arrow_top {
   display: none !important;
}

/* Today's date */
.location-search-calendar .dp__today {
   border: none !important;
}

/* Calendar container */
.location-search-calendar .dp__main {
   border: none !important;
   box-shadow: none !important;
}

/* Remove borders */
.location-search-calendar .dp__calendar_header_separator {
   display: none !important;
}

.location-search-calendar .dp__theme_light {
   border: none !important;
}

.location-search-calendar .dp__header-wrap {
   margin-bottom: 1rem !important;
}

.location-search-calendar .dp__menu_inner.dp__flex_display {
   gap: 4rem !important;
}

.location-search-calendar .dp__calendar_next {
   margin-inline-start: 0 !important;
}
</style>

<style scoped>
/* The scrolled-collapsed pill is the SAME elements shrinking in place, not a
   different, separately-rendered compact replica — matches how Airbnb's own
   bar behaves (one continuous resize) rather than a crossfade between two
   differently-sized layers. Every value here has an explicit, known
   endpoint (not measured) so the transition is deterministic. */
/* 52rem was too narrow for the Dates dropdown's two-month calendar (see
   .location-search-calendar below) — its two fixed-size months plus their
   4rem gap need ~70.6rem of actual content width, which clipped/overflowed
   the pill's edge at 52rem. Widened here rather than decoupling the
   calendar's width from the pill's. */
.location-search-pill {
    max-width: 74rem;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
    transition: max-width 320ms cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 200ms ease;
}
.location-search-pill.is-compact {
    max-width: 32rem;
    /* Most of the pill is still one click target while collapsed — cursor:
       pointer here (not on the individual sub-elements) is the fallback
       shown when hovering the pill's own background rather than one of the
       three routed regions (search/dates/filters — see interceptClick). */
    cursor: pointer;
}
/* Collapsed pill hovers as one unit — the Date+Search wrapper's own hover
   shadow (below) is scoped off while compact so the two effects don't
   double up; see the !compact check on dateHover in the template. Hovering
   any of the three routed sub-regions also satisfies this (a child being
   hovered means its ancestors are too), regardless of each one now being a
   real, independently clickable target again (see interceptClick). */
.location-search-pill.is-compact:hover {
    box-shadow: 0px 5px 16px 2px rgb(0 0 0 / 18%);
}

.location-search-input {
    padding-left: 6rem;
    transition: padding-left 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.is-compact .location-search-input {
    padding-left: 4rem;
}
/* Smaller than the global 16px input rule below, but only while idle —
   :not(:focus) keeps an actually-focused input at 16px so iOS Safari's
   zoom-on-focus-under-16px behavior (the reason that global rule exists;
   see filters.vue) never triggers, even mid-expand-transition. */
.is-compact .location-search-input:not(:focus) {
    font-size: 1.4rem !important;
}

.location-search-icon {
    transition: left 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.is-compact .location-search-icon {
    left: 1.4rem;
}

.location-search-date-btn {
    /* Matches the input's forced 16px (see filters.vue's global
       `input, select, textarea { font-size: 16px !important }`, there to
       stop iOS Safari auto-zooming on focus) rather than the `text-1xl`
       (13.5px) it would otherwise inherit — same size regardless of
       collapsed state, but was only really noticeable once Dates and the
       input sat closer together in the compact pill. */
    font-size: 16px;
    padding: 1.75rem 3rem;
    transition: padding 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.is-compact .location-search-date-btn {
    font-size: 1.4rem;
    padding: 1rem 1.75rem;
}

.location-search-submit {
    padding: 0 3rem;
    gap: 0.8rem;
    transition: padding 320ms cubic-bezier(0.2, 0.8, 0.2, 1), gap 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.is-compact .location-search-submit {
    /* A perfect circle in the collapsed pill: 3.1rem matches this button's
       actual stretched height (my-2 subtracted from the Date+Search
       wrapper's own height) — an explicit value, not aspect-ratio, so the
       shrink keeps animating smoothly via the width transition below
       (aspect-ratio-derived sizing can't interpolate the same way). */
    padding: 0;
    width: 3.1rem;
    gap: 0;
}

.location-search-submit-label {
    display: inline-block;
    max-width: 8rem;
    opacity: 1;
    overflow: hidden;
    white-space: nowrap;
    transition: max-width 320ms cubic-bezier(0.2, 0.8, 0.2, 1), opacity 180ms ease;
}
.is-compact .location-search-submit-label {
    max-width: 0;
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .location-search-pill,
    .location-search-input,
    .location-search-icon,
    .location-search-date-btn,
    .location-search-submit,
    .location-search-submit-label {
        transition: none;
    }
}
</style>
