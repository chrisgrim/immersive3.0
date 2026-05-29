/**
 * Global Vitest setup for the EI Vue 3 frontend test suite.
 *
 * This file is OWNED by the Vitest-setup agent. Other frontend agents add
 * spec files but must NOT edit this file. Keep it robust and additive so it
 * can serve every spec without per-test boilerplate.
 *
 * Runs once before each test file (configured via test.setupFiles). It wires
 * up the browser-ish globals that components and composables expect to find on
 * `window` / `global` at module-eval and runtime, mirroring what `app.js` and
 * the Blade master layout set up in the real app.
 *
 * Deliberately NOT mocked here (real libraries are needed by composable tests):
 *   - moment-timezone
 *   - dompurify
 */
import { afterEach, beforeEach, vi } from 'vitest';

// ---------------------------------------------------------------------------
// window.Laravel — server-injected bootstrap data (see Blade master layout).
// Components read window.Laravel.user; app.js seeds the root component from it.
// ---------------------------------------------------------------------------
function makeLaravel() {
    return {
        user: {
            id: 1,
            name: 'Test',
            email: 't@e.com',
            type: 'u',
        },
    };
}

// ---------------------------------------------------------------------------
// axios — app.js assigns the real axios to window.axios and sets default
// headers. Tests get a fully-mocked client so no network calls escape and so
// `window.axios.get.mockResolvedValue(...)` works out of the box.
// ---------------------------------------------------------------------------
function makeAxios() {
    const instance = {
        get: vi.fn(() => Promise.resolve({ data: {} })),
        post: vi.fn(() => Promise.resolve({ data: {} })),
        put: vi.fn(() => Promise.resolve({ data: {} })),
        patch: vi.fn(() => Promise.resolve({ data: {} })),
        delete: vi.fn(() => Promise.resolve({ data: {} })),
        request: vi.fn(() => Promise.resolve({ data: {} })),
        defaults: {
            headers: {
                common: {},
                get: {},
                post: {},
                put: {},
                patch: {},
                delete: {},
            },
            timeout: 0,
        },
        interceptors: {
            request: { use: vi.fn(), eject: vi.fn() },
            response: { use: vi.fn(), eject: vi.fn() },
        },
    };
    // axios.create() returns another client with the same shape.
    instance.create = vi.fn(() => makeAxios());
    return instance;
}

// ---------------------------------------------------------------------------
// sessionStorage / localStorage — jsdom@24 ships a Storage implementation, but
// we install a deterministic in-memory mock so behavior is identical across
// environments and is reset between tests. app.js uses sessionStorage for the
// vite:preloadError reload guard.
// ---------------------------------------------------------------------------
function makeStorage() {
    let store = {};
    return {
        getItem: vi.fn((key) => (key in store ? store[key] : null)),
        setItem: vi.fn((key, value) => {
            store[key] = String(value);
        }),
        removeItem: vi.fn((key) => {
            delete store[key];
        }),
        clear: vi.fn(() => {
            store = {};
        }),
        key: vi.fn((index) => Object.keys(store)[index] ?? null),
        get length() {
            return Object.keys(store).length;
        },
    };
}

// ---------------------------------------------------------------------------
// IntersectionObserver / ResizeObserver — not implemented by jsdom. Many
// components (lazy images, sticky bars, maps) instantiate these on mount.
// Provide no-op stubs that record the callback so tests can trigger it.
// ---------------------------------------------------------------------------
class IntersectionObserverStub {
    constructor(callback, options) {
        this.callback = callback;
        this.options = options;
        this.root = options?.root ?? null;
        this.rootMargin = options?.rootMargin ?? '';
        this.thresholds = Array.isArray(options?.threshold)
            ? options.threshold
            : [options?.threshold ?? 0];
    }

    observe = vi.fn();
    unobserve = vi.fn();
    disconnect = vi.fn();
    takeRecords = vi.fn(() => []);
}

class ResizeObserverStub {
    constructor(callback) {
        this.callback = callback;
    }

    observe = vi.fn();
    unobserve = vi.fn();
    disconnect = vi.fn();
}

// ---------------------------------------------------------------------------
// matchMedia — not implemented by jsdom; some responsive components call it.
// ---------------------------------------------------------------------------
function makeMatchMedia() {
    return vi.fn((query) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(), // deprecated but still referenced by some libs
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
    }));
}

function installGlobals() {
    window.Laravel = makeLaravel();

    window.axios = makeAxios();

    window.Sentry = { captureException: vi.fn() };

    // Storage mocks on both window and global so either access path works.
    const sessionStorageMock = makeStorage();
    const localStorageMock = makeStorage();
    Object.defineProperty(window, 'sessionStorage', {
        value: sessionStorageMock,
        configurable: true,
        writable: true,
    });
    Object.defineProperty(window, 'localStorage', {
        value: localStorageMock,
        configurable: true,
        writable: true,
    });

    global.IntersectionObserver = IntersectionObserverStub;
    window.IntersectionObserver = IntersectionObserverStub;
    global.ResizeObserver = ResizeObserverStub;
    window.ResizeObserver = ResizeObserverStub;

    if (!window.matchMedia) {
        window.matchMedia = makeMatchMedia();
    }

    // scrollTo is referenced by nav/route components; jsdom warns/throws.
    window.scrollTo = window.scrollTo ?? vi.fn();
}

// Install before every test, and reset mock call history so tests are isolated.
beforeEach(() => {
    installGlobals();
});

afterEach(() => {
    vi.clearAllMocks();
});

// Also install once immediately so anything evaluated at import time (module
// top-level code in a spec) sees the globals before the first beforeEach runs.
installGlobals();
