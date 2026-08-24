<template>
    <!-- Two-level layout, same as the event edit wizard's own (see
         Creation/Core/edit.vue): an outer fixed-height flex column with an
         independently-scrolling content area (flex-1) and a plain footer as
         the LAST flex sibling — not wrapped in the same scroll area with a
         `sticky` position. A sticky footer only pins once its scroll
         container's content overflows; when the content was shorter than
         the panel (e.g. Location mode with the map collapsed, no categories
         picked yet), it just sat in normal flow with dead space below it
         instead of at the bottom. This structure keeps it glued to the
         bottom regardless of content height. -->
    <div class="lg:h-full flex flex-col">
        <div class="flex-1 lg:overflow-y-auto">
        <!-- Same mx-auto + xl:w-2/3 narrowing the event creation/edit wizard
             uses for its own right-side form column (see Creation/Core/edit.vue,
             also Auth/user-edit.vue and the Organizer/Community edit pages) —
             a panel of short fields and toggles reads better with breathing
             room on both sides than stretched edge-to-edge. The px-6 is
             mobile-only (this component sits full-bleed there — see
             Hub/index.vue — so it needs its own inset); lg: and up it's
             already inset by the right column's own padding. -->
        <div class="mx-auto w-full xl:w-2/3 flex flex-col gap-12 pb-36 lg:pb-6 px-6 lg:px-0">
            <!-- Each part is its own plain section — no shared card, no border
                 box, no divider lines between them. -->
            <div>
                <label class="block text-2xl font-semibold mb-4" for="saved-search-name">Name</label>
                <input
                    id="saved-search-name"
                    type="text"
                    :value="draft.name"
                    @input="updateName($event.target.value)"
                    class="w-full text-xl font-medium border border-neutral-200 rounded-2xl px-6 py-5 focus:outline-none focus:border-black"
                >
            </div>

            <div>
                <div class="flex items-center gap-2 mb-5">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-full font-semibold text-base transition-colors"
                        :class="draft.criteria.searchType === 'inPerson' ? 'bg-black text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'"
                        @click="setMode('inPerson')"
                    >
                        Location
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-full font-semibold text-base transition-colors"
                        :class="draft.criteria.searchType === 'atHome' ? 'bg-black text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'"
                        @click="setMode('atHome')"
                    >
                        At Home
                    </button>
                    <span v-if="whereSummary" class="ml-auto text-lg font-medium text-neutral-500 truncate">{{ whereSummary }}</span>
                </div>

                <saved-search-location-picker
                    v-if="draft.criteria.searchType === 'inPerson'"
                    :lat="draft.criteria.lat"
                    :lng="draft.criteria.lng"
                    :city="draft.criteria.city"
                    @located="updateCriteria($event)"
                />
                <saved-search-remote-location-picker
                    v-else
                    :model-value="draft.criteria.remoteLocation"
                    @update:model-value="updateCriteria({ remoteLocation: $event })"
                />
            </div>

            <div>
                <p class="text-2xl font-semibold mb-4">Dates</p>
                <!-- Single calendar at every width (two side by side ran
                     too wide for this column) — flex justify-center keeps
                     it from sitting flush left, since a single calendar is
                     narrower than the section's own width, unlike the
                     Price range slider above it which stretches full width
                     on its own. -->
                <div class="px-7 saved-search-dates-calendar flex justify-center">
                    <VueDatePicker
                        v-model="dateRange"
                        range
                        disable-year-select
                        :enable-time-picker="false"
                        :min-date="minDate"
                        :max-date="maxDate"
                        inline
                        auto-apply
                        month-name-format="long"
                        hide-offset-dates
                        week-start="0"
                    />
                </div>
                <div class="flex items-center justify-between mt-4">
                    <p class="text-lg text-neutral-500">{{ dateRangeLabel || 'Any dates' }}</p>
                    <button
                        v-if="dateRangeLabel"
                        type="button"
                        class="text-black underline font-semibold hover:text-neutral-600"
                        @click="clearDates"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <div>
                <p class="text-2xl font-semibold mb-4">Price range</p>
                <!-- px-3 = half the 24px dot below: vue-slider-component positions
                     a dot's CENTER at the rail's 0%/100% points, so at full width
                     its 24px circle overhangs the rail (and this whole column's
                     shared edge, every other field here — the map, the price
                     bubbles, Categories — respects) by 12px on each side. That
                     doesn't create real horizontal scroll (the overhang is still
                     inside this component's own outer px-6), which is why it
                     didn't show up in a scrollWidth check, only by eye against
                     its neighbors (live report, mobile). Insetting the rail by
                     the dot's own radius keeps its OUTER edge flush with them
                     instead. -->
                <div class="px-7">
                    <vue-slider
                        v-model="priceModel"
                        :min="0"
                        :max="maxPrice"
                        :tooltip="'none'"
                        :enable-cross="false"
                        :process-style="{ backgroundColor: '#000' }"
                        :rail-style="{ backgroundColor: '#e5e5e5' }"
                        :dot-style="{ border: '2px solid black', backgroundColor: 'white', width: '24px', height: '24px', marginTop: '-.5rem' }"
                        class="w-full mb-6"
                    />
                </div>
                <div class="flex justify-between">
                    <div class="space-y-2">
                        <div class="text-base font-medium text-neutral-600">Minimum</div>
                        <div class="border border-neutral-300 rounded-full px-8 py-4">
                            <div class="text-xl">${{ priceModel[0] }}</div>
                        </div>
                    </div>
                    <div class="space-y-2 text-right">
                        <div class="text-base font-medium text-neutral-600">Maximum</div>
                        <div class="border border-neutral-300 rounded-full px-8 py-4">
                            <div class="text-xl">${{ priceModel[1] }}{{ priceModel[1] === maxPrice ? '+' : '' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <p class="text-2xl font-semibold mb-4">Categories</p>
                <Dropdown :list="availableCategories" :creatable="false" placeholder="Select categories" @onSelect="addCategory" />
                <List class="mt-6" :selections="selectedCategoryObjects" @onSelect="removeCategory" />
            </div>

            <div>
                <p class="text-2xl font-semibold mb-4">Genres</p>
                <Dropdown :list="availableGenres" :creatable="false" placeholder="Select genres" @onSelect="addGenre" />
                <List class="mt-6" :selections="selectedGenreObjects" @onSelect="removeGenre" />
            </div>

            <!-- Pilot feature (see SavedSearchController::toggleNotify()) —
                 window.Laravel.savedSearchNotificationsPilot is a UI-only
                 visibility gate, not the real enforcement, which is
                 server-side. A plain PATCH like togglePin's own, not part of
                 the draft/Save flow — the toggle takes effect immediately,
                 same as pinning a search does from the list. -->
            <div v-if="pilotEligible">
                <div class="flex items-center justify-between gap-6">
                    <div>
                        <p class="text-2xl font-semibold mb-1">Notify me about new events</p>
                        <p class="text-lg text-neutral-500">
                            Email me when a new event shows up in this search. Checked twice a day — this only covers events published after you turn it on, not existing ones.
                        </p>
                    </div>
                    <preference-toggle
                        aria-label="Notify me about new events"
                        :checked="!!search?.notifyNewEvents"
                        @change="$emit('toggle-notify')"
                    />
                </div>
            </div>
        </div>
        </div>

        <!-- Same footer language as the event creation/edit wizard's own Update
             button (see Creation/Core/edit.vue) — a plain top-bordered bar with
             a right-aligned rounded-lg button, not a floating shadow card.
             lg:-mx-20 lg:-mb-20 cancels the desktop content column's own
             p-20 (see Profile/index.vue) — that padding is meant to inset
             the FORM fields above, not this footer, which should span the
             full pane edge-to-edge like a real sticky bar rather than
             floating short of the border/edge on both sides. Mobile isn't
             wrapped in that padding (this component sits full-bleed there
             already — see the px-6 above), so this only applies at lg:.

             Mobile-only fixed bottom-0: below lg, this outer wrapper has no
             h-full (see the comment at the top of this file), so without
             this the footer just sat in normal flow at the end of the form
             — scrolled away with the content instead of staying reachable,
             and its tap target (py-3) was cramped (live report: hard to hit
             the Save button). py-6 makes the BAR taller, not the button
             itself — the button keeps its own px-6 py-3 unchanged, just
             gets more breathing room around it. pb-36 on the content div
             above compensates for this now being pulled out of flow
             (measured against the footer's actual rendered height, not
             guessed — see pb-12 below for why it's taller than the
             footer's own vertical padding alone would suggest). lg:
             reverts both to the original desktop-pane behavior. pb-12
             (not py-6 on both sides) — bottom needs more than top, clear
             of the device's own home-indicator/gesture-bar inset most
             phones now have below a fixed bottom-0 bar. -->
        <div class="flex-shrink-0 border-t border-neutral-200 bg-white flex items-center justify-end gap-4 px-6 pt-6 pb-12 fixed bottom-0 left-0 right-0 z-40 lg:static lg:py-3 lg:-mx-20 lg:-mb-20">
            <p v-if="error" class="text-red-600 mr-auto">{{ error }}</p>
            <p v-else-if="locationMissing" class="text-red-600 mr-auto">Pick a location to save this search.</p>
            <button
                type="button"
                class="px-6 py-3 rounded-lg font-semibold transition-colors"
                :class="saving || !dirty || locationMissing
                    ? 'bg-neutral-300 text-neutral-500 cursor-not-allowed'
                    : 'bg-black text-white hover:bg-neutral-800'"
                :disabled="saving || !dirty || locationMissing"
                @click="$emit('save')"
            >
                {{ saving ? 'Saving…' : 'Save' }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import VueSlider from 'vue-slider-component';
import 'vue-slider-component/theme/antd.css';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import Dropdown from '@/GlobalComponents/dropdown.vue';
import List from '@/GlobalComponents/dropdown-list.vue';
import PreferenceToggle from '@/GlobalComponents/preference-toggle.vue';
import SavedSearchRemoteLocationPicker from '../Components/saved-search-remote-location-picker.vue';
import SavedSearchLocationPicker from '../Components/saved-search-location-picker.vue';
import { parseSearchDate, formatSearchDate, formatSearchDateRangeLabel } from '@/composables/useSavedSearchDateRange';

const props = defineProps({
    // { name: string, criteria: {...} } — Hub/index.vue owns this (and
    // dirty/saving/error) so both the mobile and desktop mounts of this
    // component stay in sync with each other and so a dirty-selection-change
    // confirm() can run before the selection itself actually changes (see
    // Hub/index.vue's selectSearch).
    draft: {
        type: Object,
        required: true,
    },
    dirty: {
        type: Boolean,
        default: false,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: null,
    },
    // The full saved-search row (id, notifyNewEvents, ...) — distinct from
    // `draft`, which is only the editable name/criteria. Needed for the
    // notify-pilot toggle below, which reads/writes a real row, not the
    // in-progress draft.
    search: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['update:draft', 'save', 'toggle-notify']);

const pilotEligible = computed(() => !!window.Laravel?.savedSearchNotificationsPilot);

const updateName = (value) => {
    emit('update:draft', { ...props.draft, name: value });
};

const updateCriteria = (patch) => {
    emit('update:draft', { ...props.draft, criteria: { ...props.draft.criteria, ...patch } });
};

const setMode = (mode) => updateCriteria({ searchType: mode });

// Shown inline next to the mode toggle — the current city for Location mode;
// nothing for At Home (the picker below already shows its own resolved name,
// and a slug-derived guess here would just be a second, possibly-stale copy
// of it).
const whereSummary = computed(() => (
    props.draft.criteria.searchType === 'inPerson' ? props.draft.criteria.city : null
));

const locationMissing = computed(() => (
    props.draft.criteria.searchType === 'inPerson'
    && (!props.draft.criteria.lat || !props.draft.criteria.lng)
));

const dateRangeLabel = computed(() => formatSearchDateRangeLabel(props.draft.criteria.start, props.draft.criteria.end));

const dateRange = computed({
    get: () => {
        const start = parseSearchDate(props.draft.criteria.start);
        if (!start) return null;
        return [start, parseSearchDate(props.draft.criteria.end) || start];
    },
    // VueDatePicker's range mode can emit null (cleared) or a completed
    // [start, end] pair; a single day picked (end still null) saves as
    // start === end, matching what the live search bar's own handleSearch
    // already does for a one-day pick.
    set: (value) => {
        if (!value || !Array.isArray(value) || !value[0]) {
            updateCriteria({ start: null, end: null });
            return;
        }
        const [start, end] = value;
        updateCriteria({
            start: formatSearchDate(start),
            end: formatSearchDate(end || start),
        });
    },
});

const clearDates = () => updateCriteria({ start: null, end: null });

// Same "no past dates, capped a year out" rule the live search bar's own
// date picker enforces (location-search.vue's minDate/maxDate) — a saved
// search is still a search a user could run today, not a record of the
// past. An EXISTING search whose dates already fall outside this window
// (edited before this feature existed, or just aged past its own date)
// still displays correctly (dateRange's get() above doesn't filter by
// these bounds) — min/max-date only constrain what a NEW pick inside the
// calendar UI can select, not what's already shown.
const minDate = computed(() => {
    const date = new Date();
    date.setHours(0, 0, 0, 0);
    return date;
});
const maxDate = computed(() => {
    const date = new Date();
    date.setFullYear(date.getFullYear() + 1);
    return date;
});

// A saved search whose price was never touched stores the app-wide "no
// filter" sentinel [0, null] (see SearchStore's own default) — the slider
// can't render a null upper bound, so display it as the real max. Left
// untouched, the draft itself still holds [0, null] until the user actually
// drags the slider (see the setter), so an otherwise-untouched save doesn't
// spuriously start filtering by price.
const priceModel = computed({
    get: () => {
        const [min, max] = props.draft.criteria.price || [0, null];
        return [min ?? 0, max ?? maxPrice];
    },
    set: (value) => updateCriteria({ price: value }),
});

// A fixed, deliberately modest cap rather than the real site-wide max (which
// can run into the thousands for a handful of outlier events) — most events
// cost far less, so a $15k+ slider left the whole usable range crammed into
// its first few pixels.
const maxPrice = 1000;

const allCategories = ref([]);
const allGenres = ref([]);

// Categories are attendance-type-specific (applicable_attendance_types: 1 =
// in-person, 2 = at-home) — same rule the nav's own filters dropdown applies.
const isApplicableCategory = (category) => {
    if (!category.applicable_attendance_types) return true;
    return props.draft.criteria.searchType === 'atHome'
        ? category.applicable_attendance_types.includes(2)
        : category.applicable_attendance_types.includes(1);
};

// Excludes already-selected items — same "shrinking list" pattern the
// creation wizard's genres.vue uses, so you can't pick the same one twice.
const availableCategories = computed(() => (
    allCategories.value.filter((c) => isApplicableCategory(c) && !props.draft.criteria.categories.includes(c.id))
));
const availableGenres = computed(() => (
    allGenres.value.filter((g) => !props.draft.criteria.tags.includes(g.id))
));

const selectedCategoryObjects = computed(() => (
    props.draft.criteria.categories.map((id) => allCategories.value.find((c) => c.id === id)).filter(Boolean)
));
const selectedGenreObjects = computed(() => (
    props.draft.criteria.tags.map((id) => allGenres.value.find((g) => g.id === id)).filter(Boolean)
));

const addCategory = (item) => {
    if (props.draft.criteria.categories.includes(item.id)) return;
    updateCriteria({ categories: [...props.draft.criteria.categories, item.id] });
};
const removeCategory = (item) => {
    updateCriteria({ categories: props.draft.criteria.categories.filter((id) => id !== item.id) });
};

const addGenre = (item) => {
    if (props.draft.criteria.tags.includes(item.id)) return;
    updateCriteria({ tags: [...props.draft.criteria.tags, item.id] });
};
const removeGenre = (item) => {
    updateCriteria({ tags: props.draft.criteria.tags.filter((id) => id !== item.id) });
};

// Switching modes can leave a selected category no longer applicable — drop
// it rather than silently saving a mismatched category/attendance-type pair.
watch(() => props.draft.criteria.searchType, () => {
    const applicableIds = allCategories.value.filter(isApplicableCategory).map((c) => c.id);
    const next = props.draft.criteria.categories.filter((id) => applicableIds.includes(id));
    if (next.length !== props.draft.criteria.categories.length) {
        updateCriteria({ categories: next });
    }
});

onMounted(async () => {
    try {
        const [categoriesRes, genresRes] = await Promise.all([
            axios.get('/api/categories/active/cached'),
            axios.get('/api/genres/active/cached'),
        ]);
        allCategories.value = categoriesRes.data;
        allGenres.value = genresRes.data;
    } catch (error) {
        console.error('[saved-search-edit] failed to load filter data', error);
    }
});
</script>

<style>
/* Copied verbatim from location-search.vue's own .location-search-calendar
   overrides (that file's own comment: "Add a scoping class to all styles
   and make them !important" — vue-datepicker's default theme otherwise
   looks nothing like the rest of the site). Deliberately a COPY, not a
   shared import — this editor isn't a dropdown (no arrow/shadow/border
   chrome to strip the same way), and the live search bar is explicitly out
   of scope for this feature (see useSavedSearchDateRange.js's own comment).
   Keep both in sync by hand if the design changes. */
.saved-search-dates-calendar .dp__menu_inner .dp__menu_items {
   display: none !important;
}

/* No width:100% override here, unlike location-search.vue's copy of this
   rule — that file's calendar lives in an already width-constrained
   dropdown, so stretching to 100% just fills it. This section's own
   wrapper is as wide as the whole editor column, so the same override
   would stretch the calendar itself out to that full width instead of
   staying at its natural compact size — the flex justify-center on the
   wrapper above only has something to center if the calendar doesn't
   already consume all the space. */

.saved-search-dates-calendar .dp__month_year_wrap {
   font-size: 1.7rem !important;
   font-weight: 400 !important;
}

.saved-search-dates-calendar .dp__calendar_header {
   color: #666 !important;
   font-weight: normal !important;
   margin-bottom: 8px !important;
   font-size: 1.2rem !important;
}

.saved-search-dates-calendar .dp__calendar_row {
   margin: 0 !important;
   gap: 0 !important;
}

.saved-search-dates-calendar .dp__calendar_item {
   margin: 0 !important;
   padding: 0 !important;
   font-size: 1.4rem !important;
}

.saved-search-dates-calendar .dp__cell_inner {
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

.saved-search-dates-calendar .dp__cell_disabled {
   opacity: 0.3 !important;
   cursor: auto !important;
}

.saved-search-dates-calendar .dp__cell_inner:not(.dp--past):hover {
   border: 2px solid black !important;
   background: transparent !important;
   color: black !important;
}

.saved-search-dates-calendar .dp__active {
   background-color: black !important;
   color: white !important;
}

.saved-search-dates-calendar .dp__range_start,
.saved-search-dates-calendar .dp__range_end {
   background-color: black !important;
   color: white !important;
}

.saved-search-dates-calendar .dp__range_start {
   border-top-right-radius: 0 !important;
   border-bottom-right-radius: 0 !important;
}

.saved-search-dates-calendar .dp__range_end {
   border-top-left-radius: 0 !important;
   border-bottom-left-radius: 0 !important;
}

.saved-search-dates-calendar .dp__range_between {
   border-radius: 0 !important;
}

.saved-search-dates-calendar .dp__arrow_bottom,
.saved-search-dates-calendar .dp__arrow_top {
   display: none !important;
}

.saved-search-dates-calendar .dp__today {
   border: none !important;
}

.saved-search-dates-calendar .dp__main {
   border: none !important;
   box-shadow: none !important;
   /* Shrinks to the calendar's own natural size instead of stretching to
      fill this wrapper's full width (its library default) — the outer
      flex justify-center above only has room to center something
      narrower than the wrapper. */
   width: fit-content !important;
}

.saved-search-dates-calendar .dp__calendar_header_separator {
   display: none !important;
}

.saved-search-dates-calendar .dp__theme_light {
   border: none !important;
}

.saved-search-dates-calendar .dp__header-wrap {
   margin-bottom: 1rem !important;
}

.saved-search-dates-calendar .dp__menu_inner.dp__flex_display {
   gap: 4rem !important;
}

.saved-search-dates-calendar .dp__calendar_next {
   margin-inline-start: 0 !important;
}
</style>
