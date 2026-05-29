/**
 * Specs for the MapStore reactive singleton (resources/js/Stores/MapStore.vue).
 *
 * MapStore exposes a Vue `ref` as `.state` (so reads go through `.state.value`).
 * boundsUpdate(bounds, center) reads Leaflet-style shapes:
 *   bounds._northEast.{lat,lng}, bounds._southWest.{lat,lng}, center.{lat,lng}
 * It parseFloat's every coordinate; if any is NaN it console.warns and early-returns
 * without mutating state or notifying subscribers.
 *
 * The store is a single exported instance, so its state and subscriber list persist
 * across tests within this file. We reset both in beforeEach.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest';
import MapStore from '@/Stores/MapStore.vue';

// A valid Leaflet-style bounds/center pair.
function makeBounds(overrides = {}) {
    return {
        _northEast: { lat: 45.6, lng: -122.5, ...(overrides.northEast || {}) },
        _southWest: { lat: 45.4, lng: -122.8, ...(overrides.southWest || {}) },
    };
}
function makeCenter(overrides = {}) {
    return { lat: 45.5, lng: -122.65, ...overrides };
}

beforeEach(() => {
    // Reset reactive state in place.
    MapStore.state.value = {
        bounds: {
            northEast: { lat: null, lng: null },
            southWest: { lat: null, lng: null },
            center: [null, null],
        },
        zoom: null,
        lastUpdate: null,
    };
});

describe('MapStore — boundsUpdate (valid input)', () => {
    it('writes parsed bounds into state', () => {
        MapStore.boundsUpdate(makeBounds(), makeCenter());

        expect(MapStore.state.value.bounds).toEqual({
            northEast: { lat: 45.6, lng: -122.5 },
            southWest: { lat: 45.4, lng: -122.8 },
            center: [45.5, -122.65],
        });
    });

    it('coerces numeric-string coordinates via parseFloat', () => {
        const bounds = {
            _northEast: { lat: '10.5', lng: '20.5' },
            _southWest: { lat: '5.5', lng: '15.5' },
        };
        const center = { lat: '8', lng: '18' };

        MapStore.boundsUpdate(bounds, center);

        expect(MapStore.state.value.bounds).toEqual({
            northEast: { lat: 10.5, lng: 20.5 },
            southWest: { lat: 5.5, lng: 15.5 },
            center: [8, 18],
        });
    });

    it('notifies subscribers with the current state on a valid update', () => {
        const cb = vi.fn();
        MapStore.subscribe(cb);

        MapStore.boundsUpdate(makeBounds(), makeCenter());

        expect(cb).toHaveBeenCalledTimes(1);
        expect(cb).toHaveBeenCalledWith(MapStore.state.value);
    });
});

describe('MapStore — boundsUpdate (invalid input)', () => {
    it('warns and skips update when a center coord is missing', () => {
        const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const before = JSON.parse(JSON.stringify(MapStore.state.value.bounds));
        const cb = vi.fn();
        MapStore.subscribe(cb);

        // center is undefined → centerLat/centerLng become NaN.
        MapStore.boundsUpdate(makeBounds(), undefined);

        expect(warnSpy).toHaveBeenCalledTimes(1);
        expect(warnSpy.mock.calls[0][0]).toContain('Invalid bounds data');
        expect(MapStore.state.value.bounds).toEqual(before);
        expect(cb).not.toHaveBeenCalled();

        warnSpy.mockRestore();
    });

    // NOTE: documents an asymmetry in the source. `center` is guarded with optional
    // chaining (`center?.lat`) but `bounds` is NOT (`bounds._northEast?.lat`), so a
    // missing/undefined `bounds` throws a TypeError instead of warning + skipping like
    // every other invalid-input path. See reported bug "boundsUpdate throws on undefined
    // bounds". If the source is hardened (e.g. `bounds?._northEast`), flip this to assert
    // a warn + skip instead.
    it('throws on undefined bounds (no optional-chaining guard on the bounds arg)', () => {
        const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});

        expect(() => MapStore.boundsUpdate(undefined, makeCenter())).toThrow(TypeError);

        // It never reaches the warn/skip branch, so no warning is emitted.
        expect(warnSpy).not.toHaveBeenCalled();

        warnSpy.mockRestore();
    });

    it('warns and skips when a single coordinate is non-numeric', () => {
        const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const before = JSON.parse(JSON.stringify(MapStore.state.value.bounds));

        const bounds = makeBounds({ northEast: { lat: 'not-a-number' } });
        MapStore.boundsUpdate(bounds, makeCenter());

        expect(warnSpy).toHaveBeenCalledTimes(1);
        expect(MapStore.state.value.bounds).toEqual(before);

        warnSpy.mockRestore();
    });

    it('includes the parsed coordinates in the warning payload', () => {
        const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});

        MapStore.boundsUpdate(makeBounds(), { lat: 'x', lng: 'y' });

        const payload = warnSpy.mock.calls[0][1];
        expect(payload).toHaveProperty('parsed');
        expect(Number.isNaN(payload.parsed.centerLat)).toBe(true);
        expect(Number.isNaN(payload.parsed.centerLng)).toBe(true);

        warnSpy.mockRestore();
    });
});

describe('MapStore — subscribe / unsubscribe', () => {
    it('does NOT fire the callback immediately on subscribe', () => {
        const cb = vi.fn();

        MapStore.subscribe(cb);

        expect(cb).not.toHaveBeenCalled();
    });

    it('returns an unsubscribe fn that stops further notifications', () => {
        const cb = vi.fn();
        const unsubscribe = MapStore.subscribe(cb);

        unsubscribe();
        MapStore.boundsUpdate(makeBounds(), makeCenter());

        expect(cb).not.toHaveBeenCalled();
    });

    it('notifies multiple active subscribers', () => {
        const a = vi.fn();
        const b = vi.fn();
        const unsubA = MapStore.subscribe(a);
        MapStore.subscribe(b);

        MapStore.boundsUpdate(makeBounds(), makeCenter());
        expect(a).toHaveBeenCalledTimes(1);
        expect(b).toHaveBeenCalledTimes(1);

        // Unsubscribing one leaves the other active.
        unsubA();
        MapStore.boundsUpdate(makeBounds(), makeCenter());
        expect(a).toHaveBeenCalledTimes(1);
        expect(b).toHaveBeenCalledTimes(2);
    });
});
