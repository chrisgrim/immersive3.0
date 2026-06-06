/**
 * Loads the Google Maps JavaScript API exactly once via Google's official inline
 * bootstrap loader.
 *
 * Why this exists: the bootstrap loader is the only entry point that GUARANTEES
 * `google.maps.importLibrary` exists. Injecting the legacy `maps/api/js?...`
 * <script> tag directly — especially from several components with differing
 * params (`loading=async` vs `v=weekly`) all sharing a global `window.initMap` —
 * can leave `google.maps` defined WITHOUT `importLibrary`, which is exactly what
 * threw "google.maps.importLibrary is not a function" (EI-VUE-C). It also drops
 * the `callback=initMap` pattern, which removes the "initMap is not a function"
 * race (EI-VUE-9).
 *
 * Usage:
 *   import { importMapsLibrary } from '@/composables/useGoogleMaps';
 *   const { Place } = await importMapsLibrary('places');
 */

let bootstrapped = false;

function bootstrap() {
    if (bootstrapped) {
        return;
    }
    bootstrapped = true;

    const key = import.meta.env.VITE_GOOGLE_MAPS_KEY;

    // Google's official inline bootstrap loader (semantics preserved): it defines
    // google.maps.importLibrary, lazily loads the script on first import, loads it
    // only once, and ignores duplicate bootstraps.
    // https://developers.google.com/maps/documentation/javascript/load-maps-js-api
    ((g) => {
        let h, a, k;
        const p = 'The Google Maps JavaScript API';
        const c = 'google';
        const l = 'importLibrary';
        const q = '__ib__';
        const m = document;
        let b = window;
        b = b[c] || (b[c] = {});
        const d = b.maps || (b.maps = {});
        const r = new Set();
        const e = new URLSearchParams();
        const u = () => h || (h = new Promise(async (f, n) => {
            await (a = m.createElement('script'));
            e.set('libraries', [...r] + '');
            for (k in g) {
                e.set(k.replace(/[A-Z]/g, (t) => '_' + t[0].toLowerCase()), g[k]);
            }
            e.set('callback', c + '.maps.' + q);
            a.src = `https://maps.${c}apis.com/maps/api/js?` + e;
            d[q] = f;
            a.onerror = () => (h = n(Error(p + ' could not load.')));
            a.nonce = m.querySelector('script[nonce]')?.nonce || '';
            m.head.append(a);
        }));
        d[l] ? console.warn(p + ' only loads once. Ignoring:', g) : (d[l] = (f, ...n) => r.add(f) && u().then(() => d[l](f, ...n)));
    })({ key, v: 'weekly' });
}

/**
 * Ensure the API is bootstrapped, then import a Maps library
 * (e.g. 'places', 'maps', 'marker'). Always resolves to the library object.
 */
export async function importMapsLibrary(name) {
    bootstrap();
    return window.google.maps.importLibrary(name);
}

/** Bootstrap the loader without importing a specific library yet. */
export function loadGoogleMaps() {
    bootstrap();
}
