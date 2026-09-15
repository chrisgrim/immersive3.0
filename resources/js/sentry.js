import { captureException, init } from '@sentry/vue';
import { shouldSuppressErrorReports } from './preloadReload';

/**
 * Sentry bootstrap, loaded by app.js only when a DSN is configured.
 *
 * Its own module with NAMED imports on purpose: app.js used to
 * `import('@sentry/vue').then((Sentry) => Sentry.init(...))`, and a namespace
 * object keeps every export of the SDK alive, so session replay, tracing and
 * feedback (nothing here enables them: no integrations are registered) shipped
 * on every page, ~145 KB gzipped. Referencing just init/captureException lets
 * Vite tree-shake the rest.
 */
export function installSentry(app) {
    init({
        app,
        dsn: import.meta.env.VITE_SENTRY_DSN,
        environment: import.meta.env.VITE_SENTRY_ENVIRONMENT || 'production',
        tracesSampleRate: 0.1,
        replaysSessionSampleRate: 0,
        replaysOnErrorSampleRate: 1.0,
        // Same reasoning as the Vue errorHandler in app.js, for errors that never
        // reach it (plain listeners, unhandled rejections): once we're reloading
        // for a stale chunk, everything after is collateral (EI-VUE-10).
        beforeSend: (event) => (shouldSuppressErrorReports() ? null : event),
        // Drop noise that isn't an actionable first-party bug:
        // DuckDuckGo / in-app WKWebView bridge rejection — emitted by the
        // browser's native bridge, not our code, no stacktrace (EI-VUE-J).
        ignoreErrors: [
            'WKWebView API client did not respond to this postMessage',
            // Meta/Facebook in-app browser injects its own LCP tracker
            // (processLargestContentfulPaintEvent → sendDataToNative) that
            // calls the iOS WKWebView bridge and throws when
            // window.webkit.messageHandlers is absent. Injected into the
            // page, not shipped by us — nothing to fix (EI-VUE-S).
            'window.webkit.messageHandlers',
            // vue-leaflet's LMap debounces its moveend handler and, on
            // unmount, cancels the debounce by REJECTING any pending
            // promise with undefined — a floating promise nobody can
            // catch. Fires when a map view (e.g. the creation wizard's
            // Location step) unmounts within the ~50ms debounce window
            // after a pan/zoom. Harmless (component is already gone),
            // still present in vue-leaflet 0.10.1 (EI-VUE-V).
            'Non-Error promise rejection captured with value: undefined',
            // Microsoft Outlook SafeLinks / Office link-scanner instrumentation
            // injected into the page rejects with this string when its own
            // stale object ids miss. Third-party, no stacktrace, universally
            // ignored per Sentry docs (EI-VUE-Y).
            'Object Not Found Matching Id:',
            // The Android half of EI-VUE-S: the same injected Meta in-app
            // browser tracker, but calling the Android JS bridge instead of
            // the iOS one. Throws when the native object it was bound to has
            // been collected — i.e. the user navigated away (EI-VUE-Z).
            'Error invoking postMessage: Java object is gone',
            // A browser extension messaging a tab that has already closed.
            // Comes from extension code running in the page, never ours
            // (EI-VUE-M).
            'Invalid call to runtime.sendMessage(). Tab not found.',
        ],
        // Errors thrown entirely inside Google Maps' own minified scripts are
        // not fixable from our code — e.g. the Places attribution renderer
        // writing into a detached node (EI-VUE-H). Drop anything blamed on
        // Google's JS API rather than on our bundle.
        denyUrls: [
            /maps\.googleapis\.com/,
            /maps-api-v3/,
            // Everything the Meta in-app browser injects is served from its
            // own iabjs:// scheme. Catches future variants of EI-VUE-S/Z
            // without needing a new message string for each one.
            /^iabjs:\/\//,
        ],
    });

    // The only surface the rest of the app uses (the Vue errorHandler in
    // app.js, map.vue, location-search-mobile.vue): keep it to that.
    window.Sentry = { captureException };
}
