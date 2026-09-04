/**
 * What a search results page shows: the open list, the map's pins, and the
 * "Show more" control — shared by location.vue, location-mobile.vue and
 * all.vue, which used to carry three copies of it.
 *
 * The store (SearchStore) is the source of truth after mount: a new search
 * or a map pan replaces both list and pins; Show more replaces the list and
 * leaves the pins alone.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import SearchStore from '@/Stores/SearchStore.vue';
import { normalizeSearchResults } from '@/Stores/searchResults';

/**
 * @param {object} options
 * @param {object} options.searchedEvents  the server-rendered first page
 * @param {Array}  [options.pins]          the server-rendered pins; omit on a page with no map
 */
export function useSearchResults({ searchedEvents, pins: initialPins } = {}) {
    const events = ref(normalizeSearchResults(searchedEvents));
    const pins = ref(initialPins || []);
    const loading = ref(false);
    const hasEvents = computed(() => Boolean(events.value.data?.length));

    // Asks for pages 1..N+1 as one window that REPLACES the list — not an
    // append, see ListingsController::MAX_INITIAL_PAGES — and declines the
    // pins: same search, same pins. It goes through the store like every
    // other search, so a pan that lands after a slow click wins outright.
    // The URL records the depth only once the window has landed, and with
    // replaceState: growing the page is not turning it, so Back leaves the
    // search.
    const handleShowMore = async () => {
        const params = new URLSearchParams(window.location.search);
        const depth = (events.value.current_page || 1) + 1;
        params.set('page', depth);

        const request = new URLSearchParams(params);
        request.delete('page');
        request.set('pages', depth);
        request.set('include_pins', '0');

        try {
            const landed = await SearchStore.fetchResults(request.toString());
            if (landed) {
                window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
            }
        } catch (error) {
            console.error('Error loading more results:', error);
        }
    };

    let unsubscribe = null;

    onMounted(() => {
        // The page is the only source of the initial pins (the nav's
        // initializeFromUrl never sets them), seeded before subscribing
        // because subscribe() calls back immediately with the current state.
        if (initialPins !== undefined) {
            SearchStore.updateState({ pins: initialPins });
        }

        unsubscribe = SearchStore.subscribe((state) => {
            events.value = state.events;
            pins.value = state.pins;
            loading.value = state.loading;
        });
    });

    onUnmounted(() => unsubscribe?.());

    return { events, pins, loading, hasEvents, handleShowMore };
}
