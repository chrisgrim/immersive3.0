export const ClickOutsideDirective = {
    beforeMount(el, binding) {
        el.clickOutsideEvent = function(event) {
            console.log("Click event triggered on document");
            if (!(el === event.target || el.contains(event.target))) {
                binding.value(event);
            } else {
            }
        };
        document.addEventListener("click", el.clickOutsideEvent);
    },
    unmounted(el) {
        document.removeEventListener("click", el.clickOutsideEvent);
    },
};