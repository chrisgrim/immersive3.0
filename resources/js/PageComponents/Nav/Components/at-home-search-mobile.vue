<template>
    <div class="w-full h-full pb-8 flex flex-col gap-8">
        <!-- Type Section — same two-card structure as location-search-mobile.vue
             (a collapsed summary row for whichever section isn't active, and
             one expanded card at a time), just searching remote-location
             types instead of cities. -->
        <div
            @click="showTypeSection"
            v-if="isVisible==='dates'"
            class="relative flex w-full border rounded-4xl bg-white shadow-custom-3 p-8 justify-between">
            <div>
                <p>Type</p>
            </div>
            <div>
                <p class="font-bold">{{ searchInput }}</p>
            </div>
        </div>
        <div
            v-else
            class="flex flex-col relative w-full border shadow-custom-6 rounded-4xl bg-white p-10 overflow-auto">
            <div class="w-full mt-2">
                <h2 style="font-family: 'Montserrat', sans-serif;" class="text-4.5xl text-black leading-8 font-bold">At Home Type?</h2>
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
                        ref="typeInput"
                        class="relative text-4xl p-8 w-full font-bold z-40 bg-transparent focus:border-none focus:outline-none placeholder-slate-400 touch-manipulation"
                        v-model="searchInput"
                        placeholder="Search At Home Type"
                        @input="updateTypes"
                        @focus="dropdown = true; hasTypedSinceFocus = false"
                        @click="clearSearchInput"
                        @keydown.backspace="handleBackspace"
                        autocomplete="off"
                        type="text">
                </div>

                <!-- Type Dropdown -->
                <ul
                    class="bg-white w-full mx-0 overflow-hidden py-8 list-none"
                    v-if="dropdown"
                    @click.stop>
                    <li v-if="showRecentSearches" class="pt-4 pb-2 text-lg font-semibold text-neutral-500">
                        Recent searches
                    </li>
                    <RecentSearchesList v-if="showRecentSearches" :searches="recentSearches" />
                    <li v-if="showSuggestedHeader" class="pt-4 pb-2 text-lg font-semibold text-neutral-500" :class="{ 'border-t border-neutral-100 mt-4': showRecentSearches }">
                        Suggested types
                    </li>
                    <li
                        class="text-2xl font-mediumm pb-2 flex items-center gap-8 hover:bg-neutral-100"
                        v-for="type in types"
                        :key="type.id"
                        @click.stop="selectType(type)">
                        <div class="w-20 h-20 flex items-center justify-center bg-neutral-100 rounded-xl">
                            <svg class="w-14 h-14 transition-all duration-500 group-hover:fill-black group-hover:stroke-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                <path d="M8 10h8M8 14h5"></path>
                            </svg>
                        </div>
                        {{ type.name }}
                    </li>
                    <li v-if="!types.length" class="py-4 text-neutral-500">
                        No matching types
                    </li>
                </ul>
            </div>
        </div>

        <!-- Dates Section — identical structure/behavior to location-search-mobile.vue's;
             its global .dp__* style block (loaded alongside it in the same
             chunk) already covers this calendar too, so it isn't repeated here. -->
        <div
            @click="showDatesSection"
            v-if="isVisible==='type'"
            class="relative flex w-full border shadow-custom-3 rounded-4xl bg-white p-8 justify-between">
            <div>
                <p>When</p>
            </div>
            <div>
                <p class="font-bold">{{ date ? formatDateRange : 'Add Dates' }}</p>
            </div>
        </div>
        <div
            v-if="isVisible==='dates'"
            class="flex-grow relative w-full border shadow-custom-6 rounded-4xl bg-white p-10 overflow-auto">
            <div class="sticky top-0 bg-white z-10">
                <div class="w-full pb-4">
                    <h2 style="font-family: 'Montserrat', sans-serif;" class="text-4.5xl text-black leading-8 font-bold">When?</h2>
                </div>
                <div class="grid grid-cols-7 mt-6 text-neutral-500 font-medium text-center px-3 mb-4">
                    <p class="flex justify-center items-center text-black">S</p>
                    <p class="flex justify-center items-center text-black">M</p>
                    <p class="flex justify-center items-center text-black">T</p>
                    <p class="flex justify-center items-center text-black">W</p>
                    <p class="flex justify-center items-center text-black">T</p>
                    <p class="flex justify-center items-center text-black">F</p>
                    <p class="flex justify-center items-center text-black">S</p>
                </div>
            </div>
            <div class="calendar-container overflow-auto">
                <VueDatePicker
                    v-model="date"
                    range
                    disable-year-select
                    :multi-calendars="displayedMonths"
                    :enable-time-picker="false"
                    :dark="isDark"
                    :timezone="tz"
                    :preview-date="new Date()"
                    :min-date="minDate"
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
        </div>

        <div class="w-full bg-white p-8 flex justify-between items-center mt-auto">
            <div>
                <button @click="handleClearAll" class="underline text-4xl font-medium">Clear All</button>
            </div>
            <div>
                <button
                    @click="handleSearch"
                    :disabled="!selectedType && (!date || !date.length)"
                    :class="[
                        'py-4 px-8 rounded-2xl flex gap-4 text-4xl font-medium flex items-center',
                        (selectedType || (date && date.length)) ? 'bg-[#ff385c] text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'
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
import RecentSearchesList from './recent-searches-list.vue';
import SearchStore from '@/Stores/SearchStore.vue';
import { saveSearch } from '@/composables/useSavedSearches';

const props = defineProps({
    initialStartDate: String,
    initialEndDate: String,
});

const emit = defineEmits(['search']);

const dropdown = ref(false);
const searchInput = ref('');
const types = ref([]);
const typeInput = ref(null);
const date = ref(null);
const isVisible = ref('type');
const displayedMonths = ref(3);
const selectedType = ref(null);
const isDark = ref(false);
const tz = computed(() => Intl.DateTimeFormat().resolvedOptions().timeZone);

// Same combined-cap idea as location-search-mobile.vue / at-home-search.vue —
// Recent Searches and the default type list share one budget instead of
// each being uncapped on its own.
const recentSearches = ref([]);
const hasTypedSinceFocus = ref(false);
const showRecentSearches = computed(() => !hasTypedSinceFocus.value && recentSearches.value.length > 0);
const showSuggestedHeader = computed(() => !hasTypedSinceFocus.value && types.value.length > 0);
const DROPDOWN_TOTAL_LIMIT = 6;

// See at-home-search.vue's (desktop) identical constant — a synthetic,
// non-database entry so a user can browse every At Home event regardless of
// platform, as an explicit choice in the list rather than only discoverable
// by searching with nothing picked. slug: null is already what
// handleSearch()/nav-search-mobile.vue's handleAtHomeSearch treat as "no
// platform filter", so nothing else needs to special-case it.
const ALL_TYPES_OPTION = { id: 'all', name: 'All', slug: null };

let fetchTypesToken = 0;
const fetchTypes = async (search) => {
    const token = ++fetchTypesToken;
    try {
        const { data } = await axios.get('/api/remotelocations/public', {
            params: search ? { search } : {}
        });
        if (token !== fetchTypesToken) return;
        const results = data || [];
        if (search) {
            types.value = results;
            return;
        }
        const sliced = results.slice(0, Math.max(0, DROPDOWN_TOTAL_LIMIT - recentSearches.value.length));
        types.value = [ALL_TYPES_OPTION, ...sliced];
    } catch (error) {
        if (token !== fetchTypesToken) return;
        console.error('Error fetching remote location types:', error);
        types.value = search ? [] : [ALL_TYPES_OPTION];
    }
};

const fetchRecentSearches = async () => {
    if (!window.Laravel?.user?.id) return;
    try {
        // ?dropdown=1 — every pinned search plus at most one more (the
        // single most-recently-touched unpinned one), not every saved
        // search the user has (spelled out directly by the user: this
        // dropdown is a quick-access convenience, not the full list — that
        // lives on the Saved Search Preferences page, which fetches this
        // same endpoint without the flag).
        const { data } = await axios.get('/api/hub/saved-searches', { params: { dropdown: 1 } });
        recentSearches.value = (data.searches || []).slice(0, DROPDOWN_TOTAL_LIMIT);
        // See location-search-mobile.vue's identical fix — this fetch resolves
        // after the first (empty-recents) fetchTypes('') call below, so the
        // default type list needs to re-run to give back the slots recent
        // searches actually claimed. Only while still on the resting view —
        // a query the user has since typed must not be stomped.
        if (!hasTypedSinceFocus.value) {
            fetchTypes('');
        }
    } catch (error) {
        console.error('[recent-searches] failed to load', error);
    }
};

const updateTypes = () => {
    dropdown.value = true;
    if (!searchInput.value) {
        hasTypedSinceFocus.value = false;
        fetchTypes('');
        return;
    }
    hasTypedSinceFocus.value = true;
    fetchTypes(searchInput.value);
};

const selectType = (type) => {
    dropdown.value = false;
    searchInput.value = type.name;
    selectedType.value = { id: type.id, name: type.name, slug: type.slug };
    isVisible.value = 'type';
};

const formatDateForUrl = (dateObj) => {
    if (!dateObj) return null;
    const d = new Date(Date.UTC(
        dateObj.getFullYear(),
        dateObj.getMonth(),
        dateObj.getDate(),
        0, 0, 0
    ));
    return d.toISOString().split('T')[0] + ' 00:00:00';
};

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

const effectiveDate = computed(() => date.value || propsDateRange.value || null);

const formatDateRange = computed(() => {
    const currentDate = effectiveDate.value;
    if (!currentDate || !Array.isArray(currentDate)) return 'Dates';
    const [start, end] = currentDate;
    if (!start) return 'Dates';
    if (end && start.getTime() === end.getTime()) return formatDate(start);
    if (!end) return formatDate(start);
    return `${formatDate(start)} - ${formatDate(end)}`;
});

function formatDate(date) {
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function handleDateChange(newDate) {
    if (newDate && Array.isArray(newDate) && newDate.length === 2) {
        date.value = newDate;
    }
}

const minDate = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return today;
});

const loadMoreMonths = () => {
    displayedMonths.value = 6;
};

const handleSearch = async () => {
    if (!selectedType.value && (!date.value || !date.value.length)) {
        return;
    }

    const searchData = {
        remoteLocation: selectedType.value?.slug || null,
        remoteLocationName: selectedType.value?.name || null,
        dates: { start: null, end: null }
    };

    if (date.value && Array.isArray(date.value) && date.value.length === 2) {
        const [start, end] = date.value;
        searchData.dates = {
            start: formatDateForUrl(start),
            end: end ? formatDateForUrl(end) : formatDateForUrl(start)
        };
    }

    // Auto-save as the user's "recent search" — same call desktop's
    // at-home-search.vue makes from its own handleSearch, done here (not
    // by the parent) because nav-search-mobile.vue redirects immediately
    // after emitting, before a parent-level save could run.
    const filters = SearchStore.state.filters;
    await saveSearch(searchData.remoteLocationName || 'At Home Events', {
        searchType: 'atHome',
        remoteLocation: searchData.remoteLocation,
        start: searchData.dates.start,
        end: searchData.dates.end,
        categories: filters.categories,
        tags: filters.tags,
        price: filters.price
    });

    emit('search', searchData);
};

const showTypeSection = () => {
    isVisible.value = 'type';
    searchInput.value = selectedType.value?.name || '';
    hasTypedSinceFocus.value = false;
    dropdown.value = true;
    fetchTypes('');
};

const showDatesSection = () => {
    isVisible.value = 'dates';
    dropdown.value = false;
};

const clearSearchInput = () => {
    searchInput.value = '';
    hasTypedSinceFocus.value = false;
    dropdown.value = true;
    fetchTypes('');
};

const handleClearAll = () => {
    searchInput.value = '';
    selectedType.value = null;
    date.value = null;
    isVisible.value = 'type';
    hasTypedSinceFocus.value = false;
    dropdown.value = true;
    fetchTypes('');
};

const handleBackspace = (event) => {
    if (event.target.selectionStart === event.target.value.length &&
        searchInput.value === selectedType.value?.name) {
        searchInput.value = '';
        hasTypedSinceFocus.value = false;
        dropdown.value = true;
        fetchTypes('');
        event.preventDefault();
    }
};

const cancelSearch = () => {
    if (selectedType.value) {
        searchInput.value = selectedType.value.name;
    }
    window.dispatchEvent(new CustomEvent('hide-search'));
};

watch(isVisible, (newValue) => {
    if (newValue === 'type') {
        dropdown.value = true;
        if (!searchInput.value) {
            hasTypedSinceFocus.value = false;
            fetchTypes('');
        } else {
            updateTypes();
        }
    }
});

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const remoteLocationSlug = params.get('remoteLocation');

    if (remoteLocationSlug) {
        // Resolve the slug to a display name — the URL only carries the slug.
        // limit:20 covers the entire curated set (see at-home-search.vue's
        // identical fallback for why the default limit of 10 isn't enough).
        axios.get('/api/remotelocations/public', { params: { search: '', limit: 20 } }).then(({ data }) => {
            const match = (data || []).find((t) => t.slug === remoteLocationSlug);
            if (match) {
                searchInput.value = match.name;
                selectedType.value = { id: match.id, name: match.name, slug: match.slug };
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

    fetchRecentSearches();
    fetchTypes('');
    dropdown.value = true;
});

onUnmounted(() => {
    fetchTypesToken++;
});
</script>
