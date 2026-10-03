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
// The server keeps the largest report per view; a few are plenty.
const MAX_BEACONS = 10;
// The daily totals look for a page's time up to the end of the next UTC
// day: a report from a tab left open longer than this would come too late.
const MAX_OPEN_MS = 23 * 60 * 60 * 1000;

/**
 * Time on page for this page view (window.Laravel.analyticsView, set only
 * while it is being measured): visible time is added up across tab
 * switches, and each time the page is hidden or left a beacon carries the
 * running total (only after 5 visible seconds, only when it grew, at most
 * 10), with the deepest scroll reached. The server keeps the largest.
 */
export function watchPage(viewId) {
    if (!viewId || typeof document === 'undefined') return;

    const loadedAt = Date.now();
    let visibleMs = 0;
    let shownAt = document.visibilityState === 'visible' ? loadedAt : null;
    let depth = 0;
    let sentSeconds = 0;
    let beacons = 0;

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
        if (seconds < MIN_SECONDS || seconds <= sentSeconds || beacons >= MAX_BEACONS || Date.now() - loadedAt > MAX_OPEN_MS) return;
        sentSeconds = seconds;
        beacons++;
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
            shownAt = Date.now();
        } else {
            send();
        }
    });
    window.addEventListener('pagehide', send);
    window.addEventListener('scroll', onScroll, { passive: true });
}
