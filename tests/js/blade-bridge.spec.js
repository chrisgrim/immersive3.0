/**
 * The Share button's handler lives on `window`, assigned by an inline script
 * in show.blade.php, while the button is a Vue component emitting `share`
 * from INSIDE Blade — so every `@share="…"` is a runtime-compiled template
 * expression evaluated inside `with(_ctx)`. Vue's runtime proxy claims every
 * identifier not on its short globals allowlist, so neither a bare
 * `toggleShareModal()` nor `window.toggleShareModal?.()` can reach the window
 * function: both resolve through the component proxy to undefined and throw
 * (EI-VUE-12, EI-VUE-15).
 *
 * installBladeBridge() registers the handler on app.config.globalProperties,
 * which IS where the proxy looks. These specs pin the mechanism rather than a
 * source shape: they read the real bindings out of the Blade files, mount
 * them the way the site does (full build with the runtime compiler, in-DOM
 * template, root `createApp({ data })`), and click — once with the bridge and
 * once without, so the negative control proves the test exercises the proxy.
 */
import fs from 'node:fs';
import path from 'node:path';
// The full build. `vue` alone is the runtime-only build in tests, which
// cannot compile a template string — and the whole point is that these
// bindings are compiled at runtime.
import { createApp } from 'vue/dist/vue.esm-bundler.js';
import { installBladeBridge } from '@/bladeBridge';

const BLADES = [
    'resources/views/events/show.blade.php',
    'resources/views/events/show/header-mobile.blade.php',
];

function shareBindings() {
    const expressions = new Set();

    for (const file of BLADES) {
        const source = fs.readFileSync(path.resolve(process.cwd(), file), 'utf8');
        for (const [, expression] of source.matchAll(/@share="([^"]+)"/g)) {
            expressions.add(expression);
        }
    }

    return [...expressions];
}

// The event page, reduced to what matters: the root app show.blade.php mounts
// (app.js: createApp({ data })), a child that emits `share`, and the binding
// under test verbatim.
function mountPage(expression, { bridge = true } = {}) {
    const host = document.createElement('div');
    host.innerHTML = `<vue-event-actions @share="${expression}"></vue-event-actions>`;
    document.body.appendChild(host);

    const errors = [];
    const app = createApp({ data: () => ({ user: null, maxPrice: null }) });
    app.config.warnHandler = () => {};
    app.config.errorHandler = (err) => errors.push(err);
    app.component('vue-event-actions', {
        emits: ['share'],
        template: '<button type="button" @click="$emit(\'share\')">Share</button>',
    });

    if (bridge) {
        installBladeBridge(app);
    }

    app.mount(host);

    return {
        button: host.querySelector('button'),
        errors,
        unmount: () => {
            app.unmount();
            host.remove();
        },
    };
}

describe('the Blade @share bindings', () => {
    beforeEach(() => {
        window.toggleShareModal = vi.fn();
    });

    afterEach(() => {
        delete window.toggleShareModal;
    });

    it('exist — the Share button has not been renamed out from under this spec', () => {
        expect(shareBindings().length).toBeGreaterThan(0);
    });

    it.each(shareBindings())('%s opens the modal through the bridge', (expression) => {
        const page = mountPage(expression);

        page.button.click();

        expect(page.errors).toEqual([]);
        expect(window.toggleShareModal).toHaveBeenCalledTimes(1);
        page.unmount();
    });

    it.each(shareBindings())('%s cannot reach window without the bridge (the mechanism this guards)', (expression) => {
        const page = mountPage(expression, { bridge: false });

        page.button.click();

        expect(window.toggleShareModal).not.toHaveBeenCalled();
        expect(page.errors).toHaveLength(1);
        expect(page.errors[0]).toBeInstanceOf(TypeError);
        page.unmount();
    });

    it('is a no-op, not a throw, on a page that never defines the window handler', () => {
        delete window.toggleShareModal;
        const page = mountPage(shareBindings()[0]);

        page.button.click();

        expect(page.errors).toEqual([]);
        page.unmount();
    });
});
