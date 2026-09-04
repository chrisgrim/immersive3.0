/**
 * The one shape a page of search results takes on the client, whether it
 * came from the server-rendered page, the API, or nothing yet. The store
 * and useSearchResults both build from this so the defaults cannot drift.
 */
export const normalizeSearchResults = (raw = {}) => ({
    data: raw?.data || [],
    total: raw?.total || 0,
    current_page: raw?.current_page || 1,
    per_page: raw?.per_page || 20,
    from: raw?.from ?? null,
    to: raw?.to ?? null,
    last_page: raw?.last_page || 1,
    // Whether "Show more" is on offer — decided by the server, which also
    // knows the depth it will restore on a cold load — and the other reason
    // it can be off: the list is as deep as one search goes.
    has_more: raw?.has_more ?? false,
    limit_reached: raw?.limit_reached ?? false,
});
