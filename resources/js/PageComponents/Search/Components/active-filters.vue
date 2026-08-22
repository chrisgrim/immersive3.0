<template>
    <div v-if="hasActiveFilters" class="mb-8">
        <div class="flex flex-wrap items-center">
            <span class="text-2xl font-medium mr-2">Results filtered by:</span>
            <div class="flex flex-wrap items-center">
                <!-- Categories Pills -->
                <template v-for="(categoryId, index) in activeFilters.categories" :key="`category-${categoryId}`">
                    <div 
                        class="px-1 text-2xl cursor-pointer underline"
                        @click="openFilters"
                    >
                        {{ getCategoryName(categoryId) }}
                    </div>
                    <span v-if="index < activeFilters.categories.length - 1 || activeFilters.tags.length > 0 || activeFilters.atHome || hasPriceFilter" class="text-2xl">,&nbsp;</span>
                </template>
                
                <!-- Tags Pills -->
                <template v-for="(tagId, index) in activeFilters.tags" :key="`tag-${tagId}`">
                    <div 
                        class="px-1 text-2xl cursor-pointer underline"
                        @click="openFilters"
                    >
                        {{ getTagName(tagId) }}
                    </div>
                    <span v-if="index < activeFilters.tags.length - 1 || activeFilters.atHome || hasPriceFilter" class="text-2xl">,&nbsp;</span>
                </template>
                
                <!-- Remote Filter Pill (if active) -->
                <template v-if="activeFilters.atHome">
                    <div
                        class="px-1 text-2xl cursor-pointer underline"
                        @click="openFilters"
                    >
                        {{ remoteLocationLabel }}
                    </div>
                    <span v-if="hasPriceFilter" class="text-2xl">,&nbsp;</span>
                </template>
                
                <!-- Price Filter Pill (if active) -->
                <div 
                    v-if="hasPriceFilter"
                    class="px-1 text-2xl cursor-pointer underline"
                    @click="openFilters"
                >
                    ${{ activeFilters.price[0] }} - ${{ activeFilters.price[1] }}{{ activeFilters.price[1] === activeFilters.maxPrice ? '+' : '' }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import SearchStore from '@/Stores/SearchStore.vue';
import axios from 'axios';

const props = defineProps({
    // Server-resolved { id, name, slug } for the URL's remoteLocation slug
    // (see all.vue / ListingsController::buildSearchFilters) — lets
    // resolveRemoteLocation() below seed the store synchronously instead of
    // always fetching, which otherwise flashes the generic "Remote Events"
    // fallback before resolving to the real name on every fresh page load.
    initialRemoteLocation: {
        type: Object,
        default: null
    }
});

// Add refs for categories and tags
const categories = ref([]);
const tags = ref([]);

// Get current active filters from the SearchStore
const activeFilters = computed(() => SearchStore.state.filters);

// Check if we have any active filters
const hasActiveFilters = computed(() => {
    return (
        activeFilters.value.categories?.length > 0 || 
        activeFilters.value.tags?.length > 0 || 
        activeFilters.value.atHome === true ||
        hasPriceFilter.value
    );
});

// remoteLocation is null (no specific type — e.g. a bare atHome search with
// nothing picked), a raw slug string (hydrated from the URL, not yet
// resolved — see SearchStore.initializeFromUrl), or { slug, name } once
// resolved (see at-home-search.vue / nav-search.vue's handleAtHomeSearch).
// Falls back to the generic label for the first two cases rather than
// showing a raw slug or nothing at all.
const remoteLocationLabel = computed(() => {
    const remoteLocation = activeFilters.value.remoteLocation;
    return (remoteLocation && typeof remoteLocation === 'object' && remoteLocation.name)
        ? remoteLocation.name
        : 'Remote Events';
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

// Fetch categories and tags for displaying names
const fetchCategoriesAndTags = async () => {
    try {
        const [categoriesResponse, tagsResponse] = await Promise.all([
            axios.get('/api/categories/active/cached'),
            axios.get('/api/genres/active/cached')
        ]);
        
        categories.value = categoriesResponse.data || [];
        tags.value = tagsResponse.data || [];
    } catch (error) {
        console.error('Error fetching categories and tags:', error);
    }
};

// Helper methods to get names from IDs
const getCategoryName = (id) => {
    const category = categories.value.find(cat => cat.id === id);
    return category ? category.name : `Category ${id}`;
};

const getTagName = (id) => {
    const tag = tags.value.find(tag => tag.id === id);
    return tag ? tag.name : `Tag ${id}`;
};

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
// one running — the "Results filtered by" summary would otherwise be stuck
// on the generic "Remote Events" label forever for anyone who never expands
// the nav themselves.
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
    // the generic "Remote Events" fallback.
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

// Fetch data on mount
onMounted(() => {
    fetchCategoriesAndTags();
    resolveRemoteLocation();
});
</script>
