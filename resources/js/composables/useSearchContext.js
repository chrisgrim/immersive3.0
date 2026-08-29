import { computed } from 'vue';
import SearchStore from '@/Stores/SearchStore.vue';

/**
 * Where the current search is looking, as a phrase to hang off a sentence:
 * "in New York", "in the map area", "in Telephone", "at home", or null when
 * the search isn't scoped to anywhere in particular.
 *
 * Shared by the results header ("26 Events in New York") and the empty state
 * ("Sorry, we couldn't find any events in New York"), which have to name the
 * same place the same way — a search that reports one place when it found
 * something and another when it didn't is worse than not naming it at all.
 */
export function useSearchContext() {
    const filters = computed(() => SearchStore.state.filters);
    const location = computed(() => SearchStore.state.location);

    // remoteLocation is null (no specific type picked), a raw slug string
    // (hydrated from the URL, not yet resolved — see
    // SearchStore.initializeFromUrl), or { slug, name } once resolved. Only
    // the resolved form can be named, so the first two fall back to the
    // generic "at home" rather than printing a slug.
    const remoteLocationName = computed(() => {
        const remoteLocation = filters.value.remoteLocation;
        return (remoteLocation && typeof remoteLocation === 'object' && remoteLocation.name)
            ? remoteLocation.name
            : null;
    });

    // Ordered by what actually answers "why these events?". Dragging the map
    // outranks everything: the results are then literally whatever is in the
    // viewport, no longer the city the search started from, and naming that
    // city would be wrong.
    const context = computed(() => {
        if (location.value.live) return 'in the map area';
        if (location.value.city) return `in ${location.value.city}`;
        if (filters.value.atHome) {
            return remoteLocationName.value ? `in ${remoteLocationName.value}` : 'at home';
        }
        return null;
    });

    return { context, remoteLocationName };
}
