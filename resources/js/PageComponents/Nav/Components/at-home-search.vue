<template>
    <div class="at-home-search-pill relative flex w-full border border-slate-300 rounded-full"
    :class="[{ 'bg-neutral-200': dropdown || dateDropdown || (showFilters && !compact) }, { 'is-compact': compact }]"
    @mousedown.capture="interceptMousedown"
    @click.capture="interceptClick">
       <!-- Remote-location-type Search -->
       <div
           class="flex-1 mr-2 transition-opacity duration-200"
           :class="{ 'opacity-50': dateDropdown || (showFilters && !compact) }"
           v-click-outside="closeTypeDropdown"
       >
           <svg class="at-home-search-icon absolute top-1/2 -translate-y-1/2 left-8 w-8 h-8 fill-black z-[1002]">
               <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
           </svg>
           <input
               ref="typeInput"
               class="at-home-search-input relative text-1xl rounded-full h-full bg-transparent w-full font-bold z-40 focus:border-none focus:rounded-full focus:bg-white focus:shadow-custom-7 placeholder-black"
               v-model="searchInput"
               :placeholder="compact ? 'Search' : 'Search At Home Type'"
               @input="updateTypes"
               @focus="onInputFocus"
               autocomplete="off"
               type="text">

           <!-- Remote-location-type Dropdown -->
           <ul
               class="absolute bg-white w-full mx-0 overflow-hidden mt-8 p-8 list-none rounded-5xl shadow-custom-7"
               v-if="dropdown"
               @click.stop>
               <!-- See location-search.vue's identical branch — Recent
                    Searches sit above the types list below (not in place of
                    it), each with its own header (only at rest — see
                    showRecentSearches/showSuggestedHeader). -->
               <li v-if="showRecentSearches" class="px-8 pt-4 pb-2 text-sm font-semibold text-neutral-500">
                   Recent searches
               </li>
               <RecentSearchesList v-if="showRecentSearches" :searches="recentSearches" />
               <li v-if="showSuggestedHeader" class="px-8 pt-4 pb-2 text-sm font-semibold text-neutral-500" :class="{ 'border-t border-neutral-100 mt-4': showRecentSearches }">
                   Suggested types
               </li>
               <li
                   class="py-4 px-8 flex items-center gap-8 hover:bg-neutral-100 group"
                   v-for="type in types"
                   :key="type.id"
                   @click.stop="selectType(type)">
                   <div class="w-20 h-20 flex items-center justify-center bg-neutral-100 rounded-xl">
                       <svg class="w-14 h-14 transition-all duration-500 group-hover:fill-black group-hover:stroke-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                           <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                           <path d="M8 10h8M8 14h5"></path>
                       </svg>
                   </div>
                   <span class="transition-all group-hover:font-medium">{{ type.name }}</span>
               </li>
               <li v-if="!types.length" class="py-4 px-8 text-neutral-500">
                   No matching types
               </li>
           </ul>
       </div>

       <!-- Date + Search share one background group — see location-search.vue
            for why this is a shared wrapper rather than two separate pills. -->
       <div class="flex rounded-full transition-all duration-200" :class="[{ 'bg-white shadow-custom-7': dateDropdown || (dateHover && !compact) || showDatesNudge }, { 'opacity-50': showFilters && !compact }]">
           <div v-click-outside="closeDateDropdown">
               <button
                   @click.stop="handleDateClick"
                   @mouseenter="dateHover = true"
                   @mouseleave="dateHover = false"
                   class="text-1xl rounded-full font-bold flex items-center gap-2 at-home-search-date-btn"
               >
                   <span v-if="date">{{ formatDateRange }}</span>
                   <span v-else>Dates</span>
               </button>

               <!-- Date Picker Dropdown -->
               <div
                   v-if="dateDropdown"
                   class="absolute -left-0 w-full mt-8 bg-white rounded-5xl shadow-custom-7 p-8 z-40 at-home-search-calendar"
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
               class="at-home-search-submit rounded-full my-2 mr-2 font-semibold transition-colors flex items-center justify-center"
               :class="[
                   isOnSearchPage || selectedType || date ?
                   ['bg-default-red text-white cursor-pointer', { 'hover:bg-red-600': !compact }] :
                   'bg-black text-white cursor-not-allowed opacity-50'
               ]"
           >
               <svg class="at-home-search-submit-icon flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" width="16" height="16" aria-hidden="true">
                   <circle cx="11" cy="11" r="7"></circle>
                   <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
               </svg>
               <span class="at-home-search-submit-label">Search</span>
           </button>
       </div>

       <SearchFilterButton
           :has-active-filters="hasActiveFilters"
           :compact="compact"
           :active="showFilters && !compact"
           :vertical-inset="0.5"
           @open="openFilters"
       />

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
import { useRemoteTypeSearch, ALL_TYPES_OPTION } from '@/composables/useRemoteTypeSearch';
import axios from 'axios';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import Filters from './filters.vue';
import SearchStore from '@/Stores/SearchStore.vue';
import SearchFilterButton from './search-filter-button.vue';
import RecentSearchesList from './recent-searches-list.vue';
import { saveSearch } from '@/composables/useSavedSearches';

const props = defineProps({
    initialStartDate: {
        type: String,
        default: null
    },
    initialEndDate: {
        type: String,
        default: null
    },
    // Server-resolved { id, name, slug } for the URL's remoteLocation slug
    // (see nav-search.vue's own prop of the same name) — lets onMounted seed
    // the compact pill synchronously instead of always waiting on its own
    // fetch below, which otherwise flashes a blank "Search" placeholder (and
    // active-filters.vue's "Remote Events" fallback) before resolving to the
    // real name on every fresh page load.
    initialRemoteLocation: {
        type: Object,
        default: null
    },
    // Drives the morph into the scrolled-collapsed pill, same as location-search.vue.
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

const isProduction = import.meta.env.PROD;

const dateDropdown = ref(false);
const dateHover = ref(false);
const dropdown = ref(false);
const searchInput = ref('');
// See location-search.vue's identical DROPDOWN_TOTAL_LIMIT/defaultPlacesList
// — the at-rest view (Recent Searches + default types) shares one combined
// cap rather than each half being capped independently, so the default type
// list fills whatever's left after Recent Searches takes its share.
// Declared above the composable calls below, which read it. Vue's SFC
// compiler happens to hoist a static literal like this one, so the old
// ordering worked — but only for as long as the value stays a literal.
// Written out here so nothing depends on that optimisation.
const DROPDOWN_TOTAL_LIMIT = 6;

// Shared with the other At Home panel — see
// composables/useRemoteTypeSearch.js. ALL_TYPES_OPTION lives there too,
// so both panels offer the same synthetic "All At Home" entry.
const { types, fetchTypes, invalidateInFlight } = useRemoteTypeSearch({
    limit: DROPDOWN_TOTAL_LIMIT,
    recentCount: () => recentSearches.value.length,
});
// Programmatic focus target for interceptMousedown — see its own comment.
const typeInput = ref(null);
const date = ref(null);
const isDark = ref(false);

// See location-search.vue's identical fields for the full reasoning.
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
            fetchTypes('');
        }
    },
});
const hasTypedSinceFocus = ref(false);
const showRecentSearches = computed(() => !hasTypedSinceFocus.value && recentSearches.value.length > 0);
// See location-search.vue's identical computed — the default type list gets
// its own header only at rest, not while `types` holds live search results.
const showSuggestedHeader = computed(() => !hasTypedSinceFocus.value && types.value.length > 0);

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

const effectiveDate = computed(() => {
  if (date.value) return date.value;
  if (propsDateRange.value) return propsDateRange.value;
  return null;
});

// The currently picked remote-location type: { id, name, slug }
const selectedType = ref(null);

const emit = defineEmits(['search', 'close-search', 'dates-cleared', 'expand-requested', 'open-filters', 'close-filters', 'filters-changed']);

// Same committed/uncommitted snapshot pattern as location-search.vue — an
// unsubmitted pick shouldn't linger in the compact pill after scroll-collapse.
const committedSearchInput = ref('');
const committedSelectedType = ref(null);
const committedDate = ref(null);

const showDatesNudge = computed(() => (
    !props.compact &&
    !!selectedType.value &&
    selectedType.value !== committedSelectedType.value &&
    !date.value
));

watch(() => props.compact, (isCompact) => {
    if (isCompact) {
        dateDropdown.value = false;
        dropdown.value = false;
        if (searchInput.value !== committedSearchInput.value) {
            searchInput.value = committedSearchInput.value;
            selectedType.value = committedSelectedType.value;
        }
        if (date.value !== committedDate.value) {
            date.value = committedDate.value;
        }
    }
});

let typeSearchTimeout = null;

// See location-search.vue's identical updateLocationsToken — the debounce
// above only cancels a not-yet-fired timer; it can't stop an already-
// in-flight request (e.g. one fired before a >200ms gap between keystrokes)
// from resolving after a newer one and overwriting its results.




const updateTypes = () => {
    dropdown.value = true;

    if (!searchInput.value) {
        // Cleared back to empty (e.g. backspaced everything) — same view as
        // a fresh focus: Recent Searches first, falling back to the full
        // type list below, not a stuck "typed" state (see
        // location-search.vue's identical updateLocations fix).
        hasTypedSinceFocus.value = false;
        clearTimeout(typeSearchTimeout);
        fetchTypes('');
        return;
    }

    // Any keystroke means the user is actively searching, not just
    // reopening the pill — switch away from Recent Searches to live results.
    hasTypedSinceFocus.value = true;
    clearTimeout(typeSearchTimeout);
    typeSearchTimeout = setTimeout(() => {
        fetchTypes(searchInput.value);
    }, 200);
};

const selectType = (type) => {
    dropdown.value = false;
    searchInput.value = type.name;
    selectedType.value = { id: type.id, name: type.name, slug: type.slug };
    // Auto-advance to Dates, the natural next step — see location-search.vue's
    // identical addition in selectLocation().
    dateDropdown.value = true;
};

// Lazy, not onMounted — see location-search.vue's identical fix for why
// (this component stays mounted, v-show not v-if, across every page load
// site-wide). Fetched once, the first time the dropdown actually opens.

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const remoteLocationSlug = params.get('remoteLocation');

    if (remoteLocationSlug && props.initialRemoteLocation?.slug === remoteLocationSlug) {
        // Already resolved server-side (see ListingsController::buildSearchFilters)
        // — seed synchronously, no fetch, no flash of the wrong content.
        const match = props.initialRemoteLocation;
        searchInput.value = match.name;
        selectedType.value = { id: match.id, name: match.name, slug: match.slug };
        committedSelectedType.value = selectedType.value;
        committedSearchInput.value = searchInput.value;
        SearchStore.updateState({
            filters: { ...SearchStore.state.filters, remoteLocation: { slug: match.slug, name: match.name } }
        });
    } else if (remoteLocationSlug) {
        // Fallback for when the server didn't resolve it (shouldn't happen on
        // a normal page load, but keeps this component self-sufficient).
        // Resolve the slug to a display name — the URL only carries the
        // slug. limit:20 covers the entire curated set (see
        // publicRemoteLocations()) — the default limit of 10 only returns
        // the major-platforms-first page, which could miss the one we're
        // looking for (e.g. "telephone" sorts well past the top 10).
        axios.get('/api/remotelocations/public', { params: { search: '', limit: 20 } }).then(({ data }) => {
            const match = (data || []).find((t) => t.slug === remoteLocationSlug);
            if (match) {
                searchInput.value = match.name;
                selectedType.value = { id: match.id, name: match.name, slug: match.slug };
                committedSelectedType.value = selectedType.value;
                committedSearchInput.value = searchInput.value;
                // Resolves the store's raw slug (see SearchStore's
                // initializeFromUrl) into { slug, name } — active-filters.vue
                // reads this to show the specific type ("Telephone") in the
                // "Results filtered by" summary instead of a generic label.
                SearchStore.updateState({
                    filters: { ...SearchStore.state.filters, remoteLocation: { slug: match.slug, name: match.name } }
                });
            }
        }).catch((error) => {
            console.error('Error resolving remote location type from URL:', error);
        });
    }

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

    committedSearchInput.value = searchInput.value;
    committedSelectedType.value = selectedType.value;
    committedDate.value = date.value;
});

const formatDateRange = computed(() => {
  const currentDate = effectiveDate.value;
  if (!currentDate || !Array.isArray(currentDate)) return 'Dates';
  const [start, end] = currentDate;
  if (!start) return 'Dates';
  if (end && start.getTime() === end.getTime()) {
    return formatDate(start);
  }
  if (!end) return formatDate(start);
  return `${formatDate(start)} - ${formatDate(end)}`;
});

function formatDate(date) {
   return date.toLocaleDateString('en-US', {
       month: 'short',
       day: 'numeric'
   });
}

const tz = computed(() => Intl.DateTimeFormat().resolvedOptions().timeZone);

function handleDateChange(newDate) {
    if (newDate && Array.isArray(newDate) && newDate.length === 2) {
        date.value = newDate;
    }
}

function toggleDateDropdown() {
   dateDropdown.value = !dateDropdown.value;
   dropdown.value = false;

   if (dateDropdown.value) {
       // See location-search.vue's identical fix — Filters is parent-owned
       // state (the showFilters prop), so closing it needs an emit rather
       // than a local assignment. Without this, opening Dates while Filters
       // was already open left both panels rendered at once, overlapping.
       emit('close-filters');

       const params = new URLSearchParams(window.location.search);
       if (params.has('start') && params.has('end') && (!date.value || date.value === null)) {
           const startDate = new Date(params.get('start'));
           const endDate = new Date(params.get('end'));
           if (startDate && endDate) {
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

function closeTypeDropdown() {
   dropdown.value = false;
}

// See location-search.vue's identical openFilters — closes the other two
// surfaces (Dates, the type suggestions dropdown) so opening Filters never
// leaves one of them rendered underneath it.
function openFilters() {
    dateDropdown.value = false;
    dropdown.value = false;
    emit('open-filters');
}

const minDate = computed(() => {
   const today = new Date();
   today.setHours(0, 0, 0, 0);
   return today;
});

const maxDate = computed(() => {
   const oneYearFromNow = new Date();
   oneYearFromNow.setFullYear(oneYearFromNow.getFullYear() + 1);
   return oneYearFromNow;
});

const isOnSearchPage = computed(() => window.location.pathname.includes('/search'));

const handleSearch = async () => {
    const isSearchPage = window.location.pathname.includes('/search');

    const searchData = {
        remoteLocation: selectedType.value?.slug || null,
        // Carried alongside the slug so the in-place (same-page, no
        // redirect) update path in nav-search.vue's handleAtHomeSearch can
        // set the store's resolved { slug, name } directly, using the name
        // we already have here — no need to re-fetch it, unlike the
        // redirect path's fresh onMounted() resolution.
        remoteLocationName: selectedType.value?.name || null,
        dates: {
            start: null,
            end: null
        }
    };

    if (date.value && Array.isArray(date.value) && date.value.length === 2) {
        const [start, end] = date.value;
        const formatForUrl = (date) => {
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

    dateDropdown.value = false;
    dropdown.value = false;

    const hasDates = searchData.dates.start !== null;
    const hasType = selectedType.value !== null;

    if (!hasDates && !hasType && !isSearchPage) {
        return;
    }

    // Auto-save this as the user's "recent search" — lives here (not in
    // nav-search.vue's handleAtHomeSearch) for the same reason as
    // location-search.vue's identical call: a search from OUTSIDE a search
    // page (e.g. the homepage) redirects directly below without ever
    // emitting 'search' to the parent.
    await saveSearch(searchData.remoteLocationName || 'At Home Events', {
        searchType: 'atHome',
        remoteLocation: searchData.remoteLocation,
        start: searchData.dates.start,
        end: searchData.dates.end,
        categories: filtersState.value.categories,
        tags: filtersState.value.tags,
        price: filtersState.value.price
    });

    if (isSearchPage) {
        committedSearchInput.value = searchInput.value;
        committedSelectedType.value = selectedType.value;
        committedDate.value = date.value;
        emit('search', searchData);
        emit('close-search');
        return;
    }

    const params = new URLSearchParams();
    params.set('searchType', 'atHome');

    // searchData.remoteLocation, not hasType — hasType is true for the "All"
    // option too (a deliberately selected type, just one with no platform
    // filter attached), and URLSearchParams.set() would otherwise stringify
    // a null value into the literal text "remoteLocation=null" rather than
    // omitting the param.
    if (searchData.remoteLocation) {
        params.set('remoteLocation', searchData.remoteLocation);
    }

    if (hasDates) {
        params.set('start', searchData.dates.start);
        params.set('end', searchData.dates.end || searchData.dates.start);
    }

    window.location.href = `/index/search?${params.toString()}`;
};

const clearDates = () => {
    date.value = null;
};

function onInputFocus(event) {
    if (props.compact) {
        emit('expand-requested');
    }
    dropdown.value = true;
    // See location-search.vue's identical close-the-others fix for why.
    dateDropdown.value = false;
    emit('close-filters');
    // See location-search.vue's identical reset for why.
    hasTypedSinceFocus.value = false;
    // Select (not clear) any existing text — see location-search.vue's
    // identical fix for why: clearing outright wiped an already-committed,
    // correct value just from clicking to expand a pill that already
    // showed it (e.g. "Telephone"), even when nothing was meant to change.
    event.target.select();
    fetchTypes('');
    fetchRecentSearches();
}

// Nothing inside the collapsed pill should be independently interactive —
// see location-search.vue's identical interceptor for the full reasoning.
// Both handlers read the live `compact` prop directly (no gesture-captured
// flag needed) — mousedown's preventDefault blocks the native
// focus-on-mousedown that would otherwise flip compact mid-gesture via
// onInputFocus, so by the time click fires, compact is still whatever it
// was at mousedown either way. That also means a keyboard-triggered click
// (Enter/Space while compact, which never fires a mousedown at all) is
// handled correctly too, since there's no stale captured state to fall back
// on — the live prop is always the right answer.
function interceptMousedown(event) {
    if (!props.compact) return;
    event.preventDefault();
    event.stopPropagation();
}

function interceptClick(event) {
    if (!props.compact) return;
    event.preventDefault();
    event.stopPropagation();

    // Routed by region — Dates and Filters are real, independently
    // clickable targets again (see the removed pointer-events: none in
    // <style>), so clicking either directly opens THAT instead of always
    // opening the type search.
    if (event.target.closest('.at-home-search-date-btn')) {
        emit('expand-requested');
        toggleDateDropdown();
    } else if (event.target.closest('.search-filter-button')) {
        emit('expand-requested');
        emit('open-filters');
    } else {
        typeInput.value?.focus();
    }
}
</script>

<style>
.at-home-search-calendar .dp__menu_inner .dp__menu_items {
   display: none !important;
}
.at-home-search-calendar .dp__calendar {
   width: 100% !important;
}
.at-home-search-calendar .dp__month_year_wrap {
   font-size: 1.7rem !important;
   font-weight: 400 !important;
}
.at-home-search-calendar .dp__calendar_header {
   color: #666 !important;
   font-weight: normal !important;
   margin-bottom: 8px !important;
   font-size: 1.2rem !important;
}
.at-home-search-calendar .dp__calendar_row {
   margin: 0 !important;
   gap: 0 !important;
}
.at-home-search-calendar .dp__calendar_item {
   margin: 0 !important;
   padding: 0 !important;
   font-size: 1.4rem !important;
}
.at-home-search-calendar .dp__cell_inner {
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
.at-home-search-calendar .dp__cell_disabled {
   opacity: 0.3 !important;
   cursor: auto !important;
}
.at-home-search-calendar .dp__cell_inner:not(.dp--past):hover {
   border: 2px solid black !important;
   background: transparent !important;
   color: black !important;
}
.at-home-search-calendar .dp__active {
   background-color: black !important;
   color: white !important;
}
.at-home-search-calendar .dp__range_start,
.at-home-search-calendar .dp__range_end {
   background-color: black !important;
   color: white !important;
}
.at-home-search-calendar .dp__range_start {
   border-top-right-radius: 0 !important;
   border-bottom-right-radius: 0 !important;
}
.at-home-search-calendar .dp__range_end {
   border-top-left-radius: 0 !important;
   border-bottom-left-radius: 0 !important;
}
.at-home-search-calendar .dp__range_between {
   border-radius: 0 !important;
}
.at-home-search-calendar .dp__arrow_bottom,
.at-home-search-calendar .dp__arrow_top {
   display: none !important;
}
.at-home-search-calendar .dp__today {
   border: none !important;
}
.at-home-search-calendar .dp__main {
   border: none !important;
   box-shadow: none !important;
}
.at-home-search-calendar .dp__calendar_header_separator {
   display: none !important;
}
.at-home-search-calendar .dp__theme_light {
   border: none !important;
}
.at-home-search-calendar .dp__header-wrap {
   margin-bottom: 1rem !important;
}
.at-home-search-calendar .dp__menu_inner.dp__flex_display {
   gap: 4rem !important;
}
.at-home-search-calendar .dp__calendar_next {
   margin-inline-start: 0 !important;
}
</style>

<style scoped>
.at-home-search-pill {
    max-width: 74rem;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
    transition: max-width 320ms cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 200ms ease;
}
.at-home-search-pill.is-compact {
    max-width: 32rem;
    /* See location-search.vue's identical rule — fallback cursor for
       hovering the pill's own background outside the three routed
       sub-regions (search/dates/filters — see interceptClick). */
    cursor: pointer;
}
/* See location-search.vue's identical rule — hovering any of the three
   routed sub-regions also satisfies this. */
.at-home-search-pill.is-compact:hover {
    box-shadow: 0px 5px 16px 2px rgb(0 0 0 / 18%);
}

.at-home-search-input {
    padding-left: 6rem;
    transition: padding-left 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.is-compact .at-home-search-input {
    padding-left: 4rem;
}
.is-compact .at-home-search-input:not(:focus) {
    font-size: 1.4rem !important;
}

.at-home-search-icon {
    transition: left 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.is-compact .at-home-search-icon {
    left: 1.4rem;
}

.at-home-search-date-btn {
    font-size: 16px;
    padding: 1.75rem 3rem;
    transition: padding 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.is-compact .at-home-search-date-btn {
    font-size: 1.4rem;
    padding: 1rem 1.75rem;
}

.at-home-search-submit {
    padding: 0 3rem;
    gap: 0.8rem;
    transition: padding 320ms cubic-bezier(0.2, 0.8, 0.2, 1), gap 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.is-compact .at-home-search-submit {
    padding: 0;
    width: 3.1rem;
    gap: 0;
}

.at-home-search-submit-label {
    display: inline-block;
    max-width: 8rem;
    opacity: 1;
    overflow: hidden;
    white-space: nowrap;
    transition: max-width 320ms cubic-bezier(0.2, 0.8, 0.2, 1), opacity 180ms ease;
}
.is-compact .at-home-search-submit-label {
    max-width: 0;
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .at-home-search-pill,
    .at-home-search-input,
    .at-home-search-icon,
    .at-home-search-date-btn,
    .at-home-search-submit,
    .at-home-search-submit-label {
        transition: none;
    }
}
</style>
