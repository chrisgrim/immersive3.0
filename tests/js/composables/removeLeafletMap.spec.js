/**
 * A Leaflet map removed mid-zoom: the zoom's leftover timer must find the
 * zoom finished, not read the pane remove() deleted (EI-VUE-1M).
 */
import L from 'leaflet';
import { removeLeafletMap } from '@/composables/removeLeafletMap';

describe('removeLeafletMap', () => {
    it('leaves nothing for a zoom timer to trip over', () => {
        vi.useFakeTimers();
        const el = document.createElement('div');
        document.body.appendChild(el);
        const map = L.map(el, { center: [40, -74], zoom: 10 });

        // As Leaflet's _animateZoom leaves it: a zoom under way, its 250 ms timer set.
        map._animatingZoom = true;
        map._animateToCenter = L.latLng(40, -74);
        map._animateToZoom = 11;
        setTimeout(map._onZoomTransitionEnd.bind(map), 250);

        removeLeafletMap(map);
        expect(() => vi.runAllTimers()).not.toThrow();

        vi.useRealTimers();
        el.remove();
    });

    it('takes a pinch\'s page-wide touch listeners off with the map', () => {
        const el = document.createElement('div');
        document.body.appendChild(el);
        const map = L.map(el, { center: [40, -74], zoom: 10 });
        const pinch = map.touchZoom;

        // As Leaflet's _onTouchStart leaves it: listening on the whole page.
        L.DomEvent.on(document, 'touchmove', pinch._onTouchMove, pinch);
        L.DomEvent.on(document, 'touchend touchcancel', pinch._onTouchEnd, pinch);
        const touchKeys = () => Object.entries(document._leaflet_events || {}).filter(([k, fn]) => k.startsWith('touch') && fn);
        expect(touchKeys()).toHaveLength(3);

        removeLeafletMap(map);

        expect(touchKeys()).toHaveLength(0);
        expect(pinch._zooming).toBe(false);
        el.remove();
    });

    it('does nothing without a map', () => {
        expect(() => removeLeafletMap(null)).not.toThrow();
    });
});
