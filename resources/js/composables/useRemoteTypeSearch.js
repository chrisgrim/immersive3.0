import { ref } from 'vue';
import axios from 'axios';

/**
 * The At Home "type" (remote-location) picker's data layer, shared by
 * at-home-search.vue and at-home-search-mobile.vue.
 *
 * The two panels look and navigate differently and stay separate components;
 * this fetch was byte-identical between them, which is the kind of copy that
 * quietly grows a fix on one side only.
 */

/**
 * A synthetic entry, not a database row: it lets someone browse every At Home
 * event regardless of platform as an explicit choice in the list, rather than
 * something only discoverable by searching with nothing selected.
 *
 * `slug: null` is deliberate and load-bearing — both panels' handleSearch and
 * nav-search-mobile's handleAtHomeSearch already treat a null slug as "no
 * platform filter", so nothing needs to special-case this option. An empty
 * string would become `remoteLocation=` in the URL and filter results to
 * nothing.
 */
export const ALL_TYPES_OPTION = { id: 'all', name: 'All At Home', slug: null };

/**
 * @param {object}   options
 * @param {number}   options.limit        Shared dropdown budget. Recent searches
 *                                        are listed above these, so the default
 *                                        list gives up a slot for each one.
 * @param {Function} options.recentCount  Current number of recent-search rows,
 *                                        read at fetch time rather than captured,
 *                                        since the two requests race on mount.
 */
export function useRemoteTypeSearch({ limit, recentCount = () => 0 } = {}) {
    const types = ref([]);

    // Guards against an out-of-order response: type "z", keep typing to "zo",
    // and if the slower "z" request lands last it would otherwise render its
    // results under the newer query. Only the most recently issued request is
    // allowed to write. Bumped on unmount too (see invalidateInFlight) so a
    // late response can't touch a dead component.
    let token = 0;

    const fetchTypes = async (search) => {
        const current = ++token;

        try {
            const { data } = await axios.get('/api/remotelocations/public', {
                params: search ? { search } : {},
            });
            if (current !== token) return;

            const results = data || [];

            // A typed query is shown on its own — recent searches are hidden
            // once typing starts — so it never shares the budget and is never
            // sliced.
            if (search) {
                types.value = results;

                return;
            }

            const sliced = results.slice(0, Math.max(0, limit - recentCount()));
            types.value = [ALL_TYPES_OPTION, ...sliced];
        } catch (error) {
            if (current !== token) return;
            console.error('Error fetching remote location types:', error);
            // "All At Home" needs no server data, so it survives an outage and
            // the picker still does something useful.
            types.value = search ? [] : [ALL_TYPES_OPTION];
        }
    };

    const invalidateInFlight = () => {
        token++;
    };

    return { types, fetchTypes, invalidateInFlight };
}
