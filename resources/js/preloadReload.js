/**
 * Recovery for stale asset chunks after a deploy.
 *
 * A new deploy renames hashed asset chunks (e.g. nav-bar-mobile-<hash>.js). A browser tab
 * still running the PREVIOUS build then fails to lazy-load a chunk the new build renamed —
 * surfacing as "dynamic import failed" / "Unable to preload CSS" (Sentry EI-VUE-4, EI-VUE-6).
 * Recover by reloading once to pull the fresh build. Guards prevent a reload loop if an asset
 * is genuinely missing (not just stale): an in-memory flag for multiple chunks failing on the
 * same load, and a sessionStorage timestamp across reloads.
 *
 * Calling preventDefault() on the event tells Vite's preload helper to SWALLOW the import
 * error rather than rethrow it, so the failed `import()` resolves to `undefined` instead of
 * rejecting. Callers awaiting it then blow up on the undefined module — vue-leaflet reading
 * `(await import(icon)).default` was EI-VUE-10. Those throws are collateral from a page
 * that is already navigating away, so isReloadingAfterPreloadError() lets error reporting
 * drop everything from the moment we commit to the reload.
 */

const RELOAD_KEY = 'vite:preloadError:lastReload';

/** Don't reload again within this window — the asset is likely gone, not stale. */
const RELOAD_WINDOW_MS = 10000;

/**
 * How long the collateral errors are worth suppressing. The throws we're hiding
 * land within a tick or two of the swallowed import; this only needs to cover
 * teardown. Bounded on purpose: if the reload stalls or something cancels the
 * navigation, this document keeps running and must not stay silent forever.
 */
const SUPPRESS_WINDOW_MS = 5000;

/** Latched for the life of the document — one reload per load, no loops. */
let reloading = false;

/** Time-boxed, unlike the reload latch. */
let suppressing = false;

/**
 * True while errors are collateral from a swallowed import rather than real bugs.
 * See the preventDefault() note above.
 */
export function shouldSuppressErrorReports() {
    return suppressing;
}

export function installPreloadErrorReload(win = window) {
    win.addEventListener('vite:preloadError', (event) => {
        if (reloading) return; // several chunks failed this load — reload only once

        let last = 0;
        try { last = Number(win.sessionStorage.getItem(RELOAD_KEY) || 0); } catch (e) { /* storage blocked */ }
        // Reloaded recently: let Vite rethrow so the failure is reported rather than looped on.
        if (Date.now() - last < RELOAD_WINDOW_MS) return;

        reloading = true;
        suppressing = true;
        setTimeout(() => { suppressing = false; }, SUPPRESS_WINDOW_MS);

        try { win.sessionStorage.setItem(RELOAD_KEY, String(Date.now())); } catch (e) { /* storage blocked */ }
        event.preventDefault();
        win.location.reload();
    });
}
