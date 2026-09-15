import { defineAsyncComponent } from 'vue';
import '@vuepic/vue-datepicker/dist/main.css';

/**
 * The nav search's date picker, loaded on demand.
 *
 * The four nav search components (desktop/mobile, location/at-home) used to
 * import @vuepic/vue-datepicker statically, and since the nav is on every
 * page that put ~50 KB gzipped of calendar code into every page load for a
 * panel most visitors never open. This wrapper fetches the chunk the first
 * time the calendar is actually rendered, and the nav starts that fetch as
 * soon as the page is idle (preloadDatePickerWhenIdle) so the bytes come
 * off the critical path but the calendar is still loaded before anyone can
 * reach it: the desktop pill opens Dates by itself after a city is picked,
 * and the mobile "Dates" shortcut jumps straight to the calendar, so a
 * hover-only preload would have shown an empty panel on a cold open.
 * preloadDatePicker() is the direct form for hover/open handlers.
 *
 * The stylesheet stays a static import: it is small, and having it in place
 * before the component arrives means no unstyled flash on first open.
 *
 * No loading component on purpose: the dropdown that hosts the picker has
 * its own chrome, and an empty panel for a fraction of a second on the very
 * first open beats a "Loading..." line that then jumps.
 */
let pending = null;

export const preloadDatePicker = () => {
    pending ??= import('@vuepic/vue-datepicker').then((m) => m.default);

    return pending;
};

export const preloadDatePickerWhenIdle = () => {
    if (pending) return;

    if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(() => preloadDatePicker(), { timeout: 2000 });
    } else {
        // Safari: no requestIdleCallback. After the load event, plus a beat.
        setTimeout(preloadDatePicker, document.readyState === 'complete' ? 500 : 2000);
    }
};

export const LazyDatePicker = defineAsyncComponent(preloadDatePicker);
