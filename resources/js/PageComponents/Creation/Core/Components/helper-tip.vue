<template>
    <!-- A Helper Box: a quiet grey card under a question, in Airbnb's style.
         It never blocks a step: long tips start folded to a few lines. Shown
         while tips are on (the "Tips" pill in the top bar), or when `show` says so. -->
    <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-[0.4rem]"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0"
    >
        <aside
            v-if="visible"
            class="mt-[2.4rem] flex gap-[1.4rem] px-[2rem] py-[1.8rem] rounded-[1.2rem] bg-[#f7f7f7] text-left"
            role="note"
            :aria-label="title"
            data-helper-tip
            @vue:mounted="measure"
        >
            <svg class="w-[2.2rem] h-[2.2rem] mt-[0.1rem] flex-shrink-0 text-[#222222]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 18h6M10 21h4M12 3a6 6 0 00-3.6 10.8c.7.5 1.1 1.3 1.1 2.2h5c0-.9.4-1.7 1.1-2.2A6 6 0 0012 3z" />
            </svg>
            <div class="min-w-0 flex-1">
                <p class="text-[1.5rem] leading-[2rem] font-semibold text-[#222222]">{{ title }}</p>
                <div
                    :id="bodyId"
                    ref="body"
                    class="helper-tip-body mt-[0.4rem] text-[1.4rem] leading-[2rem] font-normal text-[#6a6a6a] overflow-hidden"
                    :class="{ 'is-folded': folded }"
                >
                    <slot />
                </div>
                <button
                    v-if="long"
                    type="button"
                    class="mt-[0.6rem] text-[1.4rem] leading-[2rem] font-semibold text-[#222222] underline"
                    :aria-expanded="!folded"
                    :aria-controls="bodyId"
                    data-helper-tip-more
                    @click="folded = !folded"
                >
                    {{ folded ? 'Show more' : 'Show less' }}
                </button>
            </div>
        </aside>
    </transition>
</template>

<script setup>
import { computed, inject, nextTick, ref } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    // Overrides the top bar's switch (the organization page has no top bar).
    show: { type: Boolean, default: null },
});

const tips = inject('helperTips', null);
const visible = computed(() => props.show ?? tips?.on ?? false);

const body = ref(null);
const folded = ref(true);
const long = ref(false);
const bodyId = `helper-tip-${Math.random().toString(36).slice(2, 9)}`;

// Folded is four lines; a tip that fits shows whole, with no button.
// Measured each time the card appears (tips can be switched on later).
const measure = async () => {
    await nextTick();
    folded.value = true;
    await nextTick();
    if (body.value) {
        long.value = body.value.scrollHeight > body.value.clientHeight + 2;
        if (!long.value) folded.value = false;
    }
};
</script>

<style scoped>
.helper-tip-body :deep(p + p) { margin-top: 0.8rem; }
.helper-tip-body :deep(strong) { color: #222222; font-weight: 600; }
.helper-tip-body.is-folded {
    max-height: 8rem;
    -webkit-mask-image: linear-gradient(to bottom, #000 55%, transparent);
    mask-image: linear-gradient(to bottom, #000 55%, transparent);
}
</style>
