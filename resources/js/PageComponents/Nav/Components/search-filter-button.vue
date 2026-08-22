<template>
    <button
        @click="$emit('open')"
        class="search-filter-button rounded-full flex items-center justify-center flex-shrink-0 transition-colors"
        :class="[
            active ? 'bg-white shadow-custom-7' : (hasActiveFilters ? 'bg-black' : ''),
            { 'hover:bg-gray-800': hasActiveFilters && !compact && !active, 'hover:bg-gray-200': !hasActiveFilters && !compact && !active },
            { 'is-compact': compact }
        ]"
        :style="{ marginTop: `${verticalInset}rem`, marginBottom: `${verticalInset}rem` }"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 32 32"
            aria-hidden="true"
            role="presentation"
            focusable="false"
            style="display: block; fill: none; height: 16px; width: 16px; stroke-width: 2.5; overflow: visible;"
            :style="{ stroke: hasActiveFilters && !active ? 'white' : 'currentcolor' }"
        >
            <path fill="none" d="M7 16H3m26 0H15M29 6h-4m-8 0H3m26 20h-4M7 16a4 4 0 1 0 8 0 4 4 0 0 0-8 0zM17 6a4 4 0 1 0 8 0 4 4 0 0 0-8 0zm0 20a4 4 0 1 0 8 0 4 4 0 0 0-8 0zm0 0H3"></path>
        </svg>
    </button>
</template>

<script setup>
defineProps({
    hasActiveFilters: {
        type: Boolean,
        default: false
    },
    // Drives the same shrink used elsewhere in the collapsed pill — this
    // button now lives inside the pill itself rather than as a separate
    // element beside it, so it needs to shrink in step with everything else.
    compact: {
        type: Boolean,
        default: false
    },
    // True while this button's own Filters dropdown is open — mirrors the
    // Dates button's identical open-state treatment in location-search.vue/
    // at-home-search.vue (that white-pill-against-a-greyed-out-bar look),
    // rather than this component tracking its own open state (the dropdown
    // itself is owned by the parent search bar, not this button).
    active: {
        type: Boolean,
        default: false
    },
    // Top/bottom margin (rem) subtracted off this button's stretched
    // height. The "right" value differs per host bar and isn't something
    // this component can infer: in the location pill, this button is a
    // sibling of the Date+Search wrapper div, not of the Search button
    // itself, so stretching to that wrapper's own height overshoots by
    // whatever inset the Search button has inside it (currently 0.5rem,
    // from its own `my-2`) — the host passes that same number here. In the
    // name bar there's no such wrapper-vs-content gap, so it passes 0.
    verticalInset: {
        type: Number,
        default: 0
    }
});

defineEmits(['open']);
</script>

<style scoped>
/* Explicit width but height set to stretch (via align-self, so it applies
   regardless of whichever alignment the host bar's own row uses) rather
   than a fixed guess — matches the Search button's actual height exactly,
   in both bars, even if that height changes later. The two bars size their
   own content differently (the location pill relies on the row's default
   stretch, the name bar centers its own row) — align-self:stretch on this
   button specifically overrides either parent's choice for just this
   child, without needing to know which one is in play. Vertical margin is
   set inline from the verticalInset prop instead of hardcoded here — see
   its definition for why the "right" inset differs per host bar. */
.search-filter-button {
    width: 5.4rem;
    margin-right: 0.5rem;
    align-self: stretch;
    transition: width 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.search-filter-button.is-compact {
    /* 3.1rem matches this button's own stretched height (same value the
       Search button's compact width now targets too — see
       location-search.vue) so both end up as perfect circles, the same
       size, in the collapsed pill. */
    width: 3.1rem;
    /* Clickable again while compact (see location-search.vue's
       interceptClick, which routes a click here to open-filters directly)
       — the hover:bg-gray-200/hover:bg-gray-800 classes are dropped above
       instead (not pointer-events: none, which would also block the
       click), so hovering this button still only shows the pill's own
       shared shadow, not its own background change on top of it. */
}

@media (prefers-reduced-motion: reduce) {
    .search-filter-button {
        transition: none;
    }
}
</style>
