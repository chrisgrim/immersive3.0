import { ref } from 'vue';
import axios from 'axios';

/**
 * The "Recent searches" block shared by all four nav search panels
 * (location-search / location-search-mobile / at-home-search /
 * at-home-search-mobile).
 *
 * Desktop and mobile deliberately stay separate components — they look
 * different and navigate differently — but this part was never one of those
 * differences. It was four copies of the same fetch, differing only in a
 * once-guard and which list to refresh afterwards, and copies like that are
 * how a fix lands on one platform and not the other (see the live report
 * behind "Fix mobile location search never saving to Recent Searches").
 *
 * @param {object}   options
 * @param {number}   options.limit     Max rows to keep. Recent searches and the
 *                                     suggestion list below them share one
 *                                     dropdown budget, so this is also what the
 *                                     caller subtracts from when slicing its own.
 * @param {Function} options.onLoaded  Run after a successful load — the caller
 *                                     re-slices its suggestion list to give back
 *                                     the slots these just claimed. The caller
 *                                     owns any guard on it (e.g. don't stomp a
 *                                     query the user has since typed).
 * @param {boolean}  options.once      Fetch at most once per component instance.
 *                                     True for the desktop panels, which mount
 *                                     with the page and stay mounted; false for
 *                                     the mobile panels, which mount fresh each
 *                                     time the sheet is opened and should show
 *                                     current data when they do.
 */
export function useRecentSearches({ limit, onLoaded = () => {}, once = false } = {}) {
    const recentSearches = ref([]);
    let fetched = false;

    const fetchRecentSearches = async () => {
        // Guests have none, and asking would 401 on every panel open.
        if (!window.Laravel?.user?.id) return;
        if (once && fetched) return;

        // Set before the request, not after: a failed fetch still counts as
        // the one attempt, matching the desktop behaviour this replaces. A
        // dropdown convenience isn't worth retrying on every keystroke.
        fetched = true;

        try {
            // ?dropdown=1 — every pinned search plus at most one more (the
            // single most-recently-touched unpinned one), not the user's full
            // list; that lives on the Saved Search Preferences page, which
            // calls this same endpoint without the flag.
            const { data } = await axios.get('/api/hub/saved-searches', { params: { dropdown: 1 } });
            recentSearches.value = (data.searches || []).slice(0, limit);
            onLoaded();
        } catch (error) {
            console.error('[recent-searches] failed to load', error);
        }
    };

    return { recentSearches, fetchRecentSearches };
}
