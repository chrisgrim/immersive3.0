/**
 * Handlers that Vue bindings written INSIDE Blade can reach.
 *
 * The event page's Share button is a Vue component emitting `share` from
 * Blade markup (`<vue-event-actions @share="…">`), so that binding is a
 * runtime-compiled template expression, evaluated inside `with(_ctx)`. Vue's
 * runtime proxy claims every identifier that is not on its short globals
 * allowlist (Math, Date, JSON, console, …) — `window` is not on it, and
 * neither is any function the page assigns to window. So a bare
 * `toggleShareModal()` resolves to `_ctx.toggleShareModal` → undefined, and
 * `window.toggleShareModal?.()` resolves `window` itself to undefined; both
 * throw on every tap (Sentry EI-VUE-12, EI-VUE-15 — three timing "fixes" in a
 * row could not have helped, because it was never a race).
 *
 * The last place that proxy looks is app.config.globalProperties, so that is
 * where a Blade binding's target has to live. Each entry here is a thin call
 * through to the window function the Blade page defines, optional-called so a
 * page that never defines it gets a no-op rather than a throw.
 *
 * Proven by tests/js/blade-bridge.spec.js, which compiles the real `@share`
 * bindings out of the Blade files, mounts them the way the site does, and
 * clicks — with and without this bridge.
 */
export function installBladeBridge(app) {
    // show.blade.php assigns window.toggleShareModal in BOTH branches of its
    // isMobile split (unlike handleShare), and it locks background scroll
    // while the modal is open.
    app.config.globalProperties.toggleShareModal = () => window.toggleShareModal?.();
}
