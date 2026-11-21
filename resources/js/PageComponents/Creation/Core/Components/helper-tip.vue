<template>
    <!-- A Helper Box. In the listing steps it stays out of the page: its words
         open in the Tips pop-up (index.vue) when the Tips button is clicked.
         With `show` (the organization page, which has no top bar) it is a quiet
         grey card on the page itself. -->
    <aside
        v-if="show"
        v-bind="$attrs"
        class="mt-[2.4rem] flex gap-[1.4rem] px-[2rem] py-[1.8rem] rounded-[1.2rem] bg-[#f7f7f7] text-left"
        role="note"
        :aria-label="title"
        data-helper-tip
    >
        <svg class="w-[2.2rem] h-[2.2rem] mt-[0.1rem] flex-shrink-0 text-[#222222]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 18h6M10 21h4M12 3a6 6 0 00-3.6 10.8c.7.5 1.1 1.3 1.1 2.2h5c0-.9.4-1.7 1.1-2.2A6 6 0 0012 3z" />
        </svg>
        <div class="min-w-0 flex-1">
            <p class="text-[1.5rem] leading-[2rem] font-semibold text-[#222222]">{{ title }}</p>
            <div class="helper-tip-body mt-[0.4rem] text-[1.4rem] leading-[2rem] font-normal text-[#6a6a6a]">
                <slot />
            </div>
        </div>
    </aside>

    <!-- The pop-up's body is always in the page (hidden while closed), so it is there to land in. -->
    <Teleport v-else-if="tips?.open" to="#helper-tips-body">
        <section class="helper-tip-section text-left" data-helper-tip>
            <h3 class="text-[2rem] leading-[2.6rem] font-semibold text-[#222222]">{{ title }}</h3>
            <div class="helper-tip-body is-modal mt-[1.2rem] text-[1.5rem] leading-[2.3rem] font-normal text-[#484848]">
                <slot />
            </div>
        </section>
    </Teleport>
</template>

<script setup>
import { inject, onMounted, onUnmounted } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps({
    title: { type: String, required: true },
    // Shows the card on the page itself (the organization page has no Tips button).
    show: { type: Boolean, default: false },
});

// Counted while on screen, so the pop-up knows whether this step has any tips.
const tips = inject('helperTips', null);
onMounted(() => { if (tips) tips.count++; });
onUnmounted(() => { if (tips) tips.count--; });
</script>

<style scoped>
.helper-tip-body :deep(p + p) { margin-top: 0.8rem; }
.helper-tip-body :deep(strong) { color: #222222; font-weight: 600; }
.helper-tip-body.is-modal :deep(p + p) { margin-top: 2rem; }
.helper-tip-section + .helper-tip-section { margin-top: 3.2rem; padding-top: 3.2rem; border-top: 1px solid #ebebeb; }
</style>
