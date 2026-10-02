/**
 * First-party analytics from the browser. Only for what the server cannot
 * see itself: a click on a search result, as the visitor leaves the page.
 * navigator.sendBeacon survives the navigation; a form-encoded body keeps it
 * a "simple" request. Fire and forget: nothing here can break a click.
 */
const SEARCH_CLICK_URL = '/api/analytics/search-click';

export function trackSearchClick(searchId, eventId, position) {
    if (!searchId || !eventId || !position) return;

    try {
        const body = new URLSearchParams({
            search_id: searchId,
            event_id: String(eventId),
            position: String(position),
        });

        if (navigator.sendBeacon?.(SEARCH_CLICK_URL, body)) return;

        fetch(SEARCH_CLICK_URL, { method: 'POST', body, keepalive: true, credentials: 'omit' }).catch(() => {});
    } catch (e) {
        // Analytics never gets in the way of opening the event.
    }
}
