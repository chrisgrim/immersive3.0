/**
 * Takes a Leaflet map down safely, even mid-zoom.
 *
 * Leaflet 1.9.4 starts a 250 ms timer at each animated zoom (its fallback for
 * a missed "transitionend") and never clears it in remove(). When the map is
 * removed during a zoom (the page closes, or the map view is swapped out), the
 * timer still runs and reads the pane remove() just deleted:
 * "Cannot read properties of undefined (reading '_leaflet_pos')" (EI-VUE-1M).
 * Marking the zoom as finished first makes that timer return at once.
 * (remove() stops any pan itself; map.stop() would fire moveend on the way out.)
 */
import L from 'leaflet';

export function removeLeafletMap(map) {
    if (!map) return;
    map._animatingZoom = false;
    // A pinch under way (phones) has a frame queued that moves the map too.
    // Its listeners sit on the whole page, so they are taken off here too
    // (Leaflet only does that at the pinch's end, which now never comes).
    const pinch = map.touchZoom;
    if (pinch) {
        if (pinch._animRequest) L.Util.cancelAnimFrame(pinch._animRequest);
        pinch._zooming = false;
        L.DomEvent.off(document, 'touchmove', pinch._onTouchMove, pinch);
        L.DomEvent.off(document, 'touchend touchcancel', pinch._onTouchEnd, pinch);
    }
    map.remove();
}
