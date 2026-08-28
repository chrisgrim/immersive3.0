<template>
    <div v-if="total > 0" class="mb-8">
        <h1 class="text-4xl font-medium text-black leading-tight">{{ headline }}</h1>

        <!-- Only the EXTRA filters go here; whatever the headline already
             names (the city, the map area, the At Home type) is not repeated. -->
        <button
            v-if="filterSummary"
            type="button"
            @click="openFilters"
            class="mt-2 flex items-center gap-2 text-xl text-gray-600 hover:text-black hover:underline underline-offset-4 focus-visible:text-black focus-visible:underline focus-visible:outline-none"
        >
            <svg class="w-6 h-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 11v5" stroke-linecap="round" />
                <path d="M12 8h.01" stroke-linecap="round" />
            </svg>
            <span>{{ filterSummary }}</span>
        </button>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import SearchStore from '@/Stores/SearchStore.vue';
import axios from 'axios';

const props = defineProps({
    // The server's own result count (searchedEvents.total), which every search
    // response has always carried — the page just never showed it, so checking
    // "have I seen everything in this category?" meant counting cards by hand.
    total: {
        type: Number,
        default: 0
    },
    // Server-resolved { id, name, slug } for the URL's remoteLocation slug
    // (see all.vue / ListingsController::buildSearchFilters) — lets
    // resolveRemoteLocation() below seed the store synchronously instead of
    // always fetching, which otherwise flashes the generic fallback before
    // resolving to the real name on every fresh page load.
    initialRemoteLocation: {
        type: Object,
        default: null
    }
});

// Get current active filters from the SearchStore
const activeFilters = computed(() => SearchStore.state.filters);
const location = computed(() => SearchStore.state.location);

// remoteLocation is null (no specific type — e.g. a bare atHome search with
// nothing picked), a raw slug string (hydrated from the URL, not yet
// resolved — see SearchStore.initializeFromUrl), or { slug, name } once
// resolved (see at-home-search.vue / nav-search.vue's handleAtHomeSearch).
// Returns null for the first two so the headline can say "at home" rather
// than "in <raw-slug>" or the old generic "Remote Events" label, which read
// as "Events in Remote Events" once it moved into a sentence.
const remoteLocationName = computed(() => {
    const remoteLocation = activeFilters.value.remoteLocation;
    return (remoteLocation && typeof remoteLocation === 'object' && remoteLocation.name)
        ? remoteLocation.name
        : null;
});

// Where the results are from, in the order that answers "why these events?".
// Dragging the map outranks everything: the results are then literally
// whatever is in the viewport, no longer the city the search started from.
const context = computed(() => {
    if (location.value.live) return 'in the map area';
    if (location.value.city) return `in ${location.value.city}`;
    if (activeFilters.value.atHome) {
        return remoteLocationName.value ? `in ${remoteLocationName.value}` : 'at home';
    }
    return null;
});

const headline = computed(() => {
    const noun = props.total === 1 ? 'Event' : 'Events';
    return [props.total.toLocaleString(), noun, context.value].filter(Boolean).join(' ');
});

// Check if price filter is active
const hasPriceFilter = computed(() => {
    return (
        activeFilters.value.price?.[0] > 0 ||
        (activeFilters.value.price?.[1] !== undefined &&
         activeFilters.value.price?.[1] < (activeFilters.value.maxPrice || 1000) &&
         activeFilters.value.maxPrice > 0)
    );
});

// The narrowing the headline does NOT already account for, named by dimension
// rather than by value: "filtered by category and price" reads at a glance,
// where the old row of value pills ("Escape Rooms & Games, Horror, $20 - $80")
// competed with the results for attention and still never said how many
// results there were.
const filterSummary = computed(() => {
    const dimensions = [];

    if (activeFilters.value.categories?.length) {
        dimensions.push(activeFilters.value.categories.length === 1 ? 'category' : 'categories');
    }
    if (activeFilters.value.tags?.length) {
        // Stored as "tags" in the store and the URL, but every user-facing
        // label calls this field Genres (same rule as saved-search-card.vue) —
        // "tag" is the backend name, not something a user should ever see.
        dimensions.push(activeFilters.value.tags.length === 1 ? 'genre' : 'genres');
    }
    if (hasPriceFilter.value) {
        dimensions.push('price');
    }

    if (!dimensions.length) return null;

    const list = dimensions.length === 1
        ? dimensions[0]
        : `${dimensions.slice(0, -1).join(', ')} and ${dimensions[dimensions.length - 1]}`;

    return `Search filtered by ${list}`;
});

// Function to open the filters modal
const openFilters = () => {
    // Dispatch a global event that the nav component can listen for
    window.dispatchEvent(new CustomEvent('open-filters'));
};

// Resolves the store's raw remoteLocation slug (hydrated straight from the
// URL by SearchStore.initializeFromUrl) into { slug, name }. Also done in
// at-home-search.vue's own onMounted() for when that search bar is actually
// on screen — but on a results page the nav starts collapsed by default
// (see nav-search.vue's isHomePage() check), so SearchAtHome never mounts
// at all unless the user explicitly expands to the At Home tab. This
// component IS always mounted on a results page, so it can't rely on that
// one running — the headline would otherwise be stuck on the generic
// "at home" wording forever for anyone who never expands the nav themselves.
const resolveRemoteLocation = async () => {
    // Reads the URL directly rather than SearchStore.state.filters.remoteLocation
    // — nav-search.vue's own onMounted() is what first hydrates that value
    // from the URL (via SearchStore.initializeFromUrl()), and mount-order
    // between separately-registered top-level components isn't something to
    // depend on here (confirmed live: this ran before that hydration had
    // happened, so the store still held its initial null and this returned
    // early every time). Reading the URL directly has no such dependency.
    const remoteLocation = new URLSearchParams(window.location.search).get('remoteLocation');
    if (!remoteLocation) return;

    // Already resolved server-side (see ListingsController::buildSearchFilters,
    // threaded through all.vue) — seed synchronously, no fetch, no flash of
    // the generic wording.
    if (props.initialRemoteLocation?.slug === remoteLocation) {
        SearchStore.updateState({
            filters: { ...SearchStore.state.filters, remoteLocation: { slug: props.initialRemoteLocation.slug, name: props.initialRemoteLocation.name } }
        });
        return;
    }

    // Fallback for when the server didn't resolve it (shouldn't happen on a
    // normal page load, but keeps this component self-sufficient).
    try {
        const { data } = await axios.get('/api/remotelocations/public', { params: { search: '', limit: 20 } });
        const match = (data || []).find((t) => t.slug === remoteLocation);
        if (match) {
            SearchStore.updateState({
                filters: { ...SearchStore.state.filters, remoteLocation: { slug: match.slug, name: match.name } }
            });
        }
    } catch (error) {
        console.error('Error resolving remote location type:', error);
    }
};

onMounted(() => {
    // The category and genre lists this component used to fetch on every
    // results page are gone with the value pills that needed them — the
    // summary line names dimensions, not values, so two requests per page
    // load bought nothing.
    resolveRemoteLocation();
});
</script>
