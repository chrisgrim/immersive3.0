/**
 * Specs for the MapStore reactive singleton (resources/js/Stores/MapStore.vue).
 *
 * MapStore exposes a Vue `ref` as `.state` (so reads go through `.state.value`).
 * boundsUpdate(bounds, center) reads Leaflet-style shapes:
 *   bounds._northEast.{lat,lng}, bounds._southWest.{lat,lng}, center.{lat,lng}
 * It parseFloat's every coordinate; if any is NaN it console.warns and early-returns
 * without mutating state or notifying subscribers.
 *
 * The store is a single exported instance, so its state ref AND its module-level
 * subscriber list persist across tests within this file. We get true isolation by
 * resetting the module registry and re-importing a fresh store in beforeEach.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest';

// Imported fresh per-test in beforeEach (see below) for module-singleton isolation.
let MapStore;

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

beforeEach(async () => {
    // MapStore keeps BOTH its state ref and its subscribers array at module scope, so a
    // plain state reset would leak subscribers between tests. Reset the module registry
    // and re-import to get a fresh store (empty subscribers + pristine default state).
    vi.resetModules();
    MapStore = (await import('@/Stores/MapStore.vue')).default;
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

    it('warns and skips on undefined bounds instead of throwing', () => {
        // `bounds` is now guarded with optional chaining (`bounds?._northEast`), so an
        // undefined bounds arg falls through to the NaN warn + skip path like every other
        // invalid input, rather than throwing a TypeError.
        const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const before = JSON.parse(JSON.stringify(MapStore.state.value.bounds));
        const cb = vi.fn();
        MapStore.subscribe(cb);

        expect(() => MapStore.boundsUpdate(undefined, makeCenter())).not.toThrow();

        expect(warnSpy).toHaveBeenCalledTimes(1);
        expect(MapStore.state.value.bounds).toEqual(before);
        expect(cb).not.toHaveBeenCalled();

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

        // Unsubscribing one leaves the other active. A different viewport:
        // a repeat of the last one is deliberately ignored (see below).
        unsubA();
        MapStore.boundsUpdate(makeBounds({ northEast: { lat: 45.7 } }), makeCenter());
        expect(a).toHaveBeenCalledTimes(1);
        expect(b).toHaveBeenCalledTimes(2);
    });
});

describe('MapStore — cross-test isolation', () => {
    // These two run in order and together prove subscribers don't leak between tests. The
    // first leaves a subscriber registered (deliberately never unsubscribed). The global
    // afterEach (tests/js/setup.js) clears mock call history, so the second test starts
    // with a zeroed `leaked`; with proper module isolation the fresh store has an empty
    // subscriber list and never calls it (0). Before the beforeEach module-reset, the
    // shared singleton still held the subscriber and would call it once (1) — failing here.
    const leaked = vi.fn();

    it('registers a subscriber and intentionally does not clean it up', () => {
        MapStore.subscribe(leaked);
        MapStore.boundsUpdate(makeBounds(), makeCenter());
        expect(leaked).toHaveBeenCalledTimes(1);
    });

    it('does not carry the previous test subscriber into a fresh store', () => {
        MapStore.boundsUpdate(makeBounds(), makeCenter());
        expect(leaked).toHaveBeenCalledTimes(0); // fresh store → never notified the leaked sub
    });
});

// Leaflet fires moveend for a size change too (invalidateSize when a mobile
// browser's toolbar collapses, the desktop full-map toggle), with the same
// rectangle as before. That is not a new search: it would reset the list to
// page 1 and rebuild every marker for nothing.
describe('MapStore — a repeat of the last viewport', () => {
    const repeatBounds = (n) => ({ _northEast: { lat: n, lng: -118.1 }, _southWest: { lat: 33.9, lng: -118.5 } });
    const repeatCenter = { lat: 34.05, lng: -118.3 };

    it('notifies for a new viewport, and again for a different one', () => {
        const cb = vi.fn();
        const unsubscribe = MapStore.subscribe(cb);

        MapStore.boundsUpdate(repeatBounds(34.61), repeatCenter);
        MapStore.boundsUpdate(repeatBounds(34.62), repeatCenter);

        expect(cb).toHaveBeenCalledTimes(2);
        unsubscribe();
    });

    it('ignores the same viewport sent twice', () => {
        const cb = vi.fn();
        const unsubscribe = MapStore.subscribe(cb);

        MapStore.boundsUpdate(repeatBounds(34.63), repeatCenter);
        MapStore.boundsUpdate(repeatBounds(34.63), repeatCenter);

        expect(cb).toHaveBeenCalledTimes(1);
        unsubscribe();
    });
});
