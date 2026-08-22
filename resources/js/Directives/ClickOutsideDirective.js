export const ClickOutsideDirective = {
    beforeMount(el, binding) {
        el.clickOutsideEvent = function(event) {
            if (!(el === event.target || el.contains(event.target))) {
                binding.value(event);
            }
        };
        // Deferred a tick: attaching synchronously can catch the very click
        // that's still bubbling to document and caused this element to
        // mount in the first place (e.g. a v-if-toggled dropdown opened by
        // that same click) — the listener would then immediately fire and
        // close it right back. A macrotask delay guarantees the triggering
        // click has fully finished dispatching first.
        el.clickOutsideTimeout = setTimeout(() => {
            document.addEventListener("click", el.clickOutsideEvent);
        }, 0);
    },
    unmounted(el) {
        clearTimeout(el.clickOutsideTimeout);
        document.removeEventListener("click", el.clickOutsideEvent);
    },
};