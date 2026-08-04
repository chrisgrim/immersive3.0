import { describe, it, expect, beforeEach, vi } from 'vitest';

/**
 * Fresh module per test — the "already reloading" latch is module state.
 * Returns the module plus a fake window whose events we can dispatch.
 */
async function freshInstall({ lastReload = null, storageThrows = false } = {}) {
    vi.resetModules();
    const mod = await import('@/preloadReload.js');

    const listeners = {};
    const store = {};
    if (lastReload !== null) store['vite:preloadError:lastReload'] = String(lastReload);

    const win = {
        addEventListener: (name, fn) => { listeners[name] = fn; },
        location: { reload: vi.fn() },
        sessionStorage: {
            getItem: (k) => {
                if (storageThrows) throw new Error('storage blocked');
                return store[k] ?? null;
            },
            setItem: (k, v) => {
                if (storageThrows) throw new Error('storage blocked');
                store[k] = v;
            },
        },
    };

    mod.installPreloadErrorReload(win);

    const firePreloadError = () => {
        const event = { preventDefault: vi.fn() };
        listeners['vite:preloadError'](event);
        return event;
    };

    return { mod, win, store, firePreloadError };
}

describe('preload error reload', () => {
    beforeEach(() => {
        vi.spyOn(Date, 'now').mockReturnValue(1_000_000);
    });

    it('reloads once and swallows the error on a first stale chunk', async () => {
        const { mod, win, firePreloadError } = await freshInstall();

        const event = firePreloadError();

        expect(event.preventDefault).toHaveBeenCalled();
        expect(win.location.reload).toHaveBeenCalledTimes(1);
        expect(mod.isReloadingAfterPreloadError()).toBe(true);
    });

    it('reloads only once when several chunks fail on the same load', async () => {
        const { win, firePreloadError } = await freshInstall();

        firePreloadError();
        const second = firePreloadError();
        const third = firePreloadError();

        expect(win.location.reload).toHaveBeenCalledTimes(1);
        // The later events are NOT swallowed, so Vite rethrows and they stay visible.
        expect(second.preventDefault).not.toHaveBeenCalled();
        expect(third.preventDefault).not.toHaveBeenCalled();
    });

    it('does not reload again within the 10s window — the asset is gone, not stale', async () => {
        const { mod, win, firePreloadError } = await freshInstall({ lastReload: 1_000_000 - 9_000 });

        const event = firePreloadError();

        expect(win.location.reload).not.toHaveBeenCalled();
        // Crucially it does NOT preventDefault, so the real error still surfaces
        // instead of the import silently resolving undefined.
        expect(event.preventDefault).not.toHaveBeenCalled();
        expect(mod.isReloadingAfterPreloadError()).toBe(false);
    });

    it('reloads again once the window has passed', async () => {
        const { win, firePreloadError } = await freshInstall({ lastReload: 1_000_000 - 11_000 });

        firePreloadError();

        expect(win.location.reload).toHaveBeenCalledTimes(1);
    });

    it('records the reload time so the guard survives the reload', async () => {
        const { store, firePreloadError } = await freshInstall();

        firePreloadError();

        expect(store['vite:preloadError:lastReload']).toBe('1000000');
    });

    it('still reloads when sessionStorage is blocked', async () => {
        const { win, firePreloadError } = await freshInstall({ storageThrows: true });

        const event = firePreloadError();

        expect(win.location.reload).toHaveBeenCalledTimes(1);
        expect(event.preventDefault).toHaveBeenCalled();
    });

    it('reports as not-reloading before any failure, so normal errors are unaffected', async () => {
        const { mod } = await freshInstall();

        expect(mod.isReloadingAfterPreloadError()).toBe(false);
    });
});
