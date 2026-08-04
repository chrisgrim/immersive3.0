import { createApp, defineAsyncComponent } from 'vue';
import '../css/app.css';
import axios from 'axios';
import { ClickOutsideDirective } from './Directives/ClickOutsideDirective';
import { installPreloadErrorReload, isReloadingAfterPreloadError } from './preloadReload';

installPreloadErrorReload();

// Loading component
const LoadingComponent = {
    template: '<div class="loading-component">Loading...</div>'
};

// Error component
const ErrorComponent = {
    template: '<div class="error-component">Error loading component. Please refresh.</div>'
};

// Async component options
const asyncOptions = {
    loading: LoadingComponent,
    error: ErrorComponent,
    delay: 200,
    timeout: 10000
};

// Navigation Components
const NavComponents = {
    NavSearch: defineAsyncComponent(() => import('./PageComponents/Nav/nav-search.vue')),
    NavSearchMobile: defineAsyncComponent(() => import('./PageComponents/Nav/nav-search-mobile.vue')),
    NavProfile: defineAsyncComponent(() => import('./PageComponents/Nav/nav-profile.vue')),
    NavBarMobile: defineAsyncComponent(() => import('./PageComponents/Nav/nav-bar-mobile.vue')),
    QuickBar: defineAsyncComponent(() => import('./PageComponents/Nav/quick-bar.vue'))
};

// Search Components
const SearchComponents = {
    Location: defineAsyncComponent(() => import('./PageComponents/Search/location.vue')),
    LocationMobile: defineAsyncComponent(() => import('./PageComponents/Search/location-mobile.vue')),
    SearchAll: defineAsyncComponent(() => import('./PageComponents/Search/all.vue'))
};

// Event Show Components
const ShowComponents = {
    ShowMore: defineAsyncComponent(() => import('./GlobalComponents/show-more.vue')),
    VideoPlayer: defineAsyncComponent(() => import('./GlobalComponents/video-player.vue')),
    ShowPurchase: defineAsyncComponent(() => import('./PageComponents/EventShow/show-purchase.vue')),
    ShowPurchaseMobile: defineAsyncComponent(() => import('./PageComponents/EventShow/show-purchase-mobile.vue')),
    ShowMap: defineAsyncComponent(() => import('./PageComponents/EventShow/show-map.vue')),
    ShowGallery: defineAsyncComponent(() => import('./PageComponents/EventShow/show-gallery.vue')),
    ShowCalendarMobile: defineAsyncComponent(() => import('./PageComponents/EventShow/show-calendar-mobile.vue')),
    SimilarEvents: defineAsyncComponent(() => import('./PageComponents/EventShow/similar-events.vue'))
};

// Auth Components
const AuthComponents = {
    ResetPassword: defineAsyncComponent(() => import('./Auth/reset-password.vue')),
    UserEdit: defineAsyncComponent(() => import('./Auth/user-edit.vue')),
    Login: defineAsyncComponent(() => import('./Auth/login.vue'))
};

// Organizer Components
const OrganizerComponents = {
    Show: defineAsyncComponent(() => import('./PageComponents/Organizer/show.vue')),
    Edit: defineAsyncComponent(() => import('./PageComponents/Organizer/edit.vue')),
    Index: defineAsyncComponent(() => import('./PageComponents/Organizer/index.vue'))
};

// Creation Components
const CreationComponents = {
    Hosting: defineAsyncComponent(() => import('./PageComponents/Creation/index.vue')),
    GettingStarted: defineAsyncComponent(() => import('./PageComponents/Creation/initial.vue')),
    HostingEvent: defineAsyncComponent(() => import('./PageComponents/Creation/Core/index.vue')),
    HostingEventEdit: defineAsyncComponent(() => import('./PageComponents/Creation/Core/edit.vue'))
};

// Community Components
const CommunityComponents = {
    Show: defineAsyncComponent(() => import('./PageComponents/Curated/Communities/show.vue')),
    Edit: defineAsyncComponent(() => import('./PageComponents/Curated/Communities/edit.vue')),
    Listings: defineAsyncComponent(() => import('./PageComponents/Curated/Communities/listings.vue')),
    Index: defineAsyncComponent(() => import('./PageComponents/Curated/Communities/index.vue')),
    Create: defineAsyncComponent(() => import('./PageComponents/Curated/Communities/create.vue'))
};

// Date picker with proper initialization
const DatePickerComponent = defineAsyncComponent({
    loader: () => import('@vuepic/vue-datepicker'),
    loadingComponent: LoadingComponent,
    errorComponent: ErrorComponent,
    delay: 0,
    timeout: 30000
});

// Post Components with date picker dependency
const PostComponents = {
    Edit: defineAsyncComponent({
        loader: () => import('./PageComponents/Curated/Posts/edit.vue'),
        loadingComponent: LoadingComponent,
        errorComponent: ErrorComponent,
        delay: 200,
        timeout: 30000
    }),
    Create: defineAsyncComponent({
        loader: () => import('./PageComponents/Curated/Posts/create.vue'),
        loadingComponent: LoadingComponent,
        errorComponent: ErrorComponent,
        delay: 200,
        timeout: 30000
    })
};

const app = createApp({
    data() {
        return {
            user: window.Laravel.user,
            maxPrice: null
        };
    }
});

// Catch any component-level error so one bad component can't blank the page.
// Sentry's Vue integration also installs a handler, but having our own ensures
// dev/no-DSN builds still log instead of failing silently.
app.config.errorHandler = (err, instance, info) => {
    console.error('[Vue]', info, err);
    // We've already committed to reloading for a stale chunk. Swallowed imports
    // now resolve to undefined, so anything thrown here is collateral from a page
    // that is on its way out — e.g. vue-leaflet reading .default off an undefined
    // icon module (EI-VUE-10). Don't report it.
    if (isReloadingAfterPreloadError()) return;
    // Dynamic-import fetch failures are already owned by the vite:preloadError
    // reload path (preloadReload.js). Anything reaching here has already reloaded
    // once and re-failed within 10s — a transient client-network tail (flaky
    // connection, in-app browser), not a first-party bug. Don't report (EI-VUE-2).
    if (/Failed to fetch dynamically imported module|Unable to preload/.test(err?.message)) return;
    if (window.Sentry?.captureException) {
        window.Sentry.captureException(err, { extra: { vueInfo: info } });
    }
};

// Sentry (skip if no DSN configured)
if (import.meta.env.VITE_SENTRY_DSN) {
    import('@sentry/vue').then((Sentry) => {
        Sentry.init({
            app,
            dsn: import.meta.env.VITE_SENTRY_DSN,
            environment: import.meta.env.VITE_SENTRY_ENVIRONMENT || 'production',
            tracesSampleRate: 0.1,
            replaysSessionSampleRate: 0,
            replaysOnErrorSampleRate: 1.0,
            // Same reasoning as the Vue errorHandler above, for errors that never
            // reach it (plain listeners, unhandled rejections): once we're reloading
            // for a stale chunk, everything after is collateral (EI-VUE-10).
            beforeSend: (event) => (isReloadingAfterPreloadError() ? null : event),
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
        window.Sentry = Sentry;
    });
}

// Setup axios
window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.timeout = 30000;

// Register date picker first
app.component('VueDatePicker', DatePickerComponent);

// Register components
app.component('vue-nav-search', NavComponents.NavSearch);
app.component('vue-nav-search-mobile', NavComponents.NavSearchMobile);
app.component('vue-nav-profile', NavComponents.NavProfile);
app.component('vue-nav-bar-mobile', NavComponents.NavBarMobile);
app.component('vue-quick-bar', NavComponents.QuickBar);

app.component('vue-search-location', SearchComponents.Location);
app.component('vue-search-location-mobile', SearchComponents.LocationMobile);
app.component('vue-search-all', SearchComponents.SearchAll);

app.component('vue-show-more', ShowComponents.ShowMore);
app.component('vue-video-player', ShowComponents.VideoPlayer);
app.component('vue-show-purchase', ShowComponents.ShowPurchase);
app.component('vue-show-purchase-mobile', ShowComponents.ShowPurchaseMobile);
app.component('vue-show-map', ShowComponents.ShowMap);
app.component('vue-show-gallery', ShowComponents.ShowGallery);
app.component('vue-show-calendar-mobile', ShowComponents.ShowCalendarMobile);
app.component('vue-similar-events', ShowComponents.SimilarEvents);

app.component('vue-reset-password', AuthComponents.ResetPassword);
app.component('vue-user-profile', AuthComponents.UserEdit);
app.component('vue-user-login', AuthComponents.Login);
app.component('vue-api-tokens', defineAsyncComponent(() => import('./PageComponents/Settings/api-tokens.vue')));

app.component('vue-inbox', defineAsyncComponent(() => import('./PageComponents/Messaging/inbox.vue')));
app.component('vue-admin', defineAsyncComponent(() => import('./PageComponents/Admin/index.vue')));

app.component('vue-organizer', OrganizerComponents.Show);
app.component('vue-organizer-edit', OrganizerComponents.Edit);
app.component('vue-organizer-index', OrganizerComponents.Index);

app.component('vue-hosting', CreationComponents.Hosting);
app.component('vue-getting-started', CreationComponents.GettingStarted);
app.component('vue-hosting-event', CreationComponents.HostingEvent);
app.component('vue-hosting-event-edit', CreationComponents.HostingEventEdit);

app.component('vue-community-show', CommunityComponents.Show);
app.component('vue-community-edit', CommunityComponents.Edit);
app.component('vue-community-listings', CommunityComponents.Listings);
app.component('vue-community-index', CommunityComponents.Index);
app.component('vue-community-create', CommunityComponents.Create);

app.component('vue-post-edit', PostComponents.Edit);
app.component('vue-post-create', PostComponents.Create);

app.directive('click-outside', ClickOutsideDirective);

app.mount('#app');