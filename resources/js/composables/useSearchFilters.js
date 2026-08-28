import { computed } from 'vue';
import SearchStore from '@/Stores/SearchStore.vue';

/**
 * What the filter panel is currently narrowing a search by.
 *
 * "Filters" here means exactly the three dimensions the filter panel owns —
 * category, genre and price. Dates and location live in the search bar, not
 * the panel, so telling someone to "remove filters" over a date range would
 * send them looking in the wrong place.
 *
 * Shared because two places have to agree on it: the results header names
 * these dimensions in its summary line, and the empty state uses them to
 * decide whether the reason for no results is the filters. The price test is
 * the subtle one — the slider sits at [0, maxPrice] by default, which is not
 * a filter — and duplicating it is how the two would drift apart.
 */
export function useSearchFilters() {
    const filters = computed(() => SearchStore.state.filters);

    const hasPriceFilter = computed(() => {
        return (
            filters.value.price?.[0] > 0 ||
            (filters.value.price?.[1] !== undefined &&
             filters.value.price?.[1] < (filters.value.maxPrice || 1000) &&
             filters.value.maxPrice > 0)
        );
    });

    // Named by dimension rather than by value: "category and price" reads at a
    // glance where a row of value pills competes with the results themselves.
    // The store calls genres "tags"; users never see that word, so neither
    // does this (same rule as saved-search-card.vue).
    const filterDimensions = computed(() => {
        const dimensions = [];

        if (filters.value.categories?.length) {
            dimensions.push(filters.value.categories.length === 1 ? 'category' : 'categories');
        }
        if (filters.value.tags?.length) {
            dimensions.push(filters.value.tags.length === 1 ? 'genre' : 'genres');
        }
        if (hasPriceFilter.value) {
            dimensions.push('price');
        }

        return dimensions;
    });

    const hasActiveFilters = computed(() => filterDimensions.value.length > 0);

    const filterSummary = computed(() => {
        const dimensions = filterDimensions.value;

        if (!dimensions.length) return null;

        const list = dimensions.length === 1
            ? dimensions[0]
            : `${dimensions.slice(0, -1).join(', ')} and ${dimensions[dimensions.length - 1]}`;

        return `Search filtered by ${list}`;
    });

    // Opens the filter panel — the nav listens for this.
    const openFilters = () => {
        window.dispatchEvent(new CustomEvent('open-filters'));
    };

    return { hasActiveFilters, filterDimensions, filterSummary, openFilters };
}
