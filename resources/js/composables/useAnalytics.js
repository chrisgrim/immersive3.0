/**
 * First-party analytics from the browser. Only for what the server cannot
 * see itself: a click on a search result, how long a page was looked at
 * (both sent as the visitor leaves), and that a browser really ran the page.
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
    // Back to a page kept in the back/forward cache: no visibilitychange in
    // some browsers (mobile Safari), so start counting again here.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted && document.visibilityState === 'visible' && shownAt === null) shownAt = Date.now();
    });
    window.addEventListener('scroll', onScroll, { passive: true });
}

const PAGE_PING_URL = '/api/analytics/page-ping';

/**
 * The load ping for this page view (window.Laravel.analyticsView, while
 * analyticsPing is on): once, as soon as this script runs (a browser running
 * the page's JavaScript is the confirmation) and the page is visible, so a
 * prerendered or background tab that is never shown sends nothing (if it is
 * shown later, it pings then; leaving it tries once more). Tells the server
 * whether the browser reports being driven by a script
 * (navigator.webdriver).
 */
export function pingPage(viewId) {
    if (!viewId || typeof document === 'undefined') return;

    let sent = false;
    const shown = () => document.visibilityState === 'visible' && !document.prerendering;

    const ping = () => {
        if (sent || !shown()) return;
        sent = true;
        document.removeEventListener('visibilitychange', ping);
        document.removeEventListener('prerenderingchange', ping);
        window.removeEventListener('pagehide', ping);
        try {
            const body = new URLSearchParams({ view_id: viewId, wd: navigator.webdriver === true ? '1' : '0' });
            if (!navigator.sendBeacon?.(PAGE_PING_URL, body)) {
                fetch(PAGE_PING_URL, { method: 'POST', body, keepalive: true, credentials: 'omit' }).catch(() => {});
            }
        } catch (e) {
            // Analytics never gets in the way of the page.
        }
    };

    document.addEventListener('visibilitychange', ping);
    document.addEventListener('prerenderingchange', ping);
    // A safety net: leaving a page that was shown but not yet pinged.
    window.addEventListener('pagehide', ping);
    ping();
}
