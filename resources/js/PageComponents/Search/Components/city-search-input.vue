<template>
    <div class="relative border border-slate-300 rounded-full" :class="{ 'bg-neutral-200': open }" v-click-outside="close">
        <svg class="city-search-icon absolute top-1/2 -translate-y-1/2 left-8 w-8 h-8 fill-black z-[1002]" :class="{ 'is-compact': compact }">
            <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
        </svg>
        <input
            ref="inputEl"
            class="city-search-input relative text-1xl rounded-full bg-transparent w-full font-bold z-40 py-6 placeholder-black"
            :class="{ 'is-compact': compact }"
            :value="modelValue"
            :placeholder="placeholder"
            @input="handleInput($event.target.value)"
            @focus="handleFocus"
            autocomplete="off"
            type="text"
        >

        <ul
            class="absolute top-full left-0 bg-white w-full mx-0 overflow-hidden mt-2 p-8 list-none rounded-5xl shadow-custom-7 z-50"
            v-if="open"
            @click.stop
        >
            <RecentSearchesList v-if="showRecentSearches" :searches="recentSearches" />
            <li
                class="py-4 px-8 flex items-center gap-8 hover:bg-neutral-100 group"
                v-for="place in predictions"
                :key="place.place_id"
                @click.stop="select(place)"
            >
                <div class="w-20 h-20 flex items-center justify-center bg-neutral-100 rounded-xl">
                    <svg class="w-14 h-14 transition-all duration-500 group-hover:fill-black group-hover:stroke-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s-8-5-8-11a8 8 0 1 1 16 0c0 6-8 11-8 11z"></path>
                        <circle cx="12" cy="11" r="3"></circle>
                    </svg>
                </div>
                <span class="transition-all group-hover:font-medium">{{ place.description }}</span>
            </li>
        </ul>
    </div>
</template>

<script setup>
// City-autocomplete for the Profile saved-search editor's location picker
// (saved-search-location-picker.vue). Built to match the nav's own
// location-search.vue markup/classes/Places calls as closely as possible so
// it reads as "the same search as nav" — but nav does NOT import this
// component, it has its own separate inline implementation; the two are
// kept in sync by hand, not by sharing code. Only the piece owning the
// input + dropdown + Places predictions/selection mechanic lives here —
// dates, compact-mode pill, filters, submit/redirect, and recent-searches
// fetching are all nav-specific and were never part of this extraction.
import { ref, onMounted } from 'vue';
import { importMapsLibrary } from '@/composables/useGoogleMaps';
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective';
import RecentSearchesList from '@/PageComponents/Nav/Components/recent-searches-list.vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Search by City',
    },
    // Drives the same collapsed-pill CSS nav's own compact mode uses —
    // irrelevant outside the nav pill (the Hub picker never sets this).
    compact: {
        type: Boolean,
        default: false,
    },
    // Recent Searches sits above the live/default predictions — only nav
    // wants this (the Hub editor is already inside one specific saved
    // search, so showing a shortcut back to others there would be noise).
    showRecentSearches: {
        type: Boolean,
        default: false,
    },
    recentSearches: {
        type: Array,
        default: () => [],
    },
});

// `select` fires twice per pick: immediately with the typed description and
// null lat/lng (nav uses this to auto-advance to Dates without waiting on
// the network), then again once the precise place details resolve. Callers
// that only care about the final precise value can just use the second
// emission's non-null lat/lng.
const emit = defineEmits(['update:modelValue', 'select', 'focus', 'input']);

const vClickOutside = ClickOutsideDirective;

function defaultPlaces() {
    return [
        { place_id: 'ChIJOwg_06VPwokRYv534QaPC8g', description: 'New York, NY' },
        { place_id: 'ChIJE9on3F3HwoAR9AhGJW_fL-I', description: 'Los Angeles, CA' },
        { place_id: 'ChIJdd4hrwug2EcRmSrV3Vo6llI', description: 'London, UK' },
        { place_id: 'ChIJIQBpAG2ahYAR_6128GcTUEo', description: 'San Francisco, CA' },
        { place_id: 'ChIJzxcfI6PYa4cR1jaKJ_j0jhE', description: 'Denver, CO' },
    ];
}

const open = ref(false);
const predictions = ref(defaultPlaces());
const inputEl = ref(null);
let autoComplete = null;

const initGoogleMaps = async () => {
    try {
        const { AutocompleteService } = await importMapsLibrary('places');
        autoComplete = new AutocompleteService();
    } catch (error) {
        console.error('[city-search-input] failed to init Google Maps Places API', error);
    }
};

const fetchPredictions = async (value) => {
    if (!autoComplete) return;
    try {
        const { predictions: results } = await autoComplete.getPlacePredictions({
            input: value,
            types: ['(cities)'],
        });
        predictions.value = results || defaultPlaces();
    } catch (error) {
        console.error('[city-search-input] failed to fetch place predictions', error);
        predictions.value = defaultPlaces();
    }
};

const handleInput = (value) => {
    emit('update:modelValue', value);
    emit('input', value);
    open.value = value.length > 0;

    if (!value) {
        predictions.value = defaultPlaces();
        return;
    }
    fetchPredictions(value);
};

const select = async (place) => {
    open.value = false;
    const cityParts = place.description.split(',');
    emit('update:modelValue', cityParts[0].trim());
    emit('select', { name: place.description, lat: null, lng: null });

    try {
        const { Place } = await importMapsLibrary('places');
        const placeResult = new Place({ id: place.place_id });
        await placeResult.fetchFields({ fields: ['displayName', 'formattedAddress', 'location'] });
        setPlace(placeResult);
    } catch (error) {
        console.error('[city-search-input] failed to fetch place details', error);
    }
};

const setPlace = (place) => {
    let lat = null;
    let lng = null;

    if (place.location) {
        if (typeof place.location.lat === 'function') {
            lat = place.location.lat();
            lng = place.location.lng();
        } else if (typeof place.location.lat === 'number') {
            lat = place.location.lat;
            lng = place.location.lng;
        }
    }

    let placeName = place.displayName?.text || place.formattedAddress || 'Unknown location';
    if (placeName.endsWith(', USA')) {
        placeName = placeName.replace(', USA', '');
    }

    emit('update:modelValue', placeName.split(',')[0].trim());
    emit('select', { name: placeName, lat, lng });
    open.value = false;
};

const handleFocus = (event) => {
    open.value = true;
    event.target.select();
    predictions.value = defaultPlaces();
    emit('focus');
};

const close = () => {
    open.value = false;
};

onMounted(() => {
    initGoogleMaps();
});

defineExpose({ close });
</script>

<style scoped>
.city-search-input {
    padding-left: 6rem;
    transition: padding-left 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.city-search-input.is-compact {
    padding-left: 4rem;
    pointer-events: none;
}
.city-search-input.is-compact:not(:focus) {
    font-size: 1.4rem !important;
}
.city-search-icon {
    transition: left 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.city-search-icon.is-compact {
    left: 1.4rem;
}

@media (prefers-reduced-motion: reduce) {
    .city-search-input,
    .city-search-icon {
        transition: none;
    }
}
</style>
