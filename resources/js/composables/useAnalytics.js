/**
 * First-party analytics from the browser. Only for what the server cannot
 * see itself: a click on a search result, and how long a page was looked
 * at, both sent as the visitor leaves.
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

const PAGE_LEAVE_URL = '/api/analytics/page-leave';
const MIN_SECONDS = 5;

/**
 * Time on page for this page view (window.Laravel.analyticsView, set only
 * while it is being measured): visible time is added up across tab
 * switches, and ONE beacon goes when the page is first hidden or left,
 * only after 5 visible seconds, with the deepest scroll reached.
 */
export function watchPage(viewId) {
    if (!viewId || typeof document === 'undefined') return;

    let visibleMs = 0;
    let shownAt = document.visibilityState === 'visible' ? Date.now() : null;
    let depth = 0;
    let sent = false;

    const onScroll = () => {
        const height = document.documentElement.scrollHeight - window.innerHeight;
        depth = Math.max(depth, height > 0 ? Math.round((window.scrollY / height) * 100) : 100);
    };

    const send = () => {
        if (shownAt !== null) {
            visibleMs += Date.now() - shownAt;
            shownAt = null;
        }
        const seconds = Math.round(visibleMs / 1000);
        if (sent || seconds < MIN_SECONDS) return;
        sent = true;
        try {
            const body = new URLSearchParams({ view_id: viewId, seconds: String(seconds), depth: String(Math.min(100, depth)) });
            if (!navigator.sendBeacon?.(PAGE_LEAVE_URL, body)) {
                fetch(PAGE_LEAVE_URL, { method: 'POST', body, keepalive: true, credentials: 'omit' }).catch(() => {});
            }
        } catch (e) {
            // Analytics never gets in the way of leaving a page.
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            if (!sent) shownAt = Date.now();
        } else {
            send();
        }
    });
    window.addEventListener('pagehide', send);
    window.addEventListener('scroll', onScroll, { passive: true });
}
