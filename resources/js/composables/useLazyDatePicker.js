import { defineAsyncComponent } from 'vue';
import '@vuepic/vue-datepicker/dist/main.css';

/**
 * The nav search's date picker, loaded on demand.
 *
 * The four nav search components (desktop/mobile, location/at-home) used to
 * import @vuepic/vue-datepicker statically, and since the nav is on every
 * page that put ~50 KB gzipped of calendar code into every page load for a
 * panel most visitors never open. This wrapper fetches the chunk the first
 * time the calendar is actually rendered; preloadDatePicker() lets a
 * component start that fetch earlier (hovering the Dates button, opening the
 * mobile search) so the calendar is normally ready by the time it is shown.
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

export const LazyDatePicker = defineAsyncComponent(preloadDatePicker);
