<template>
    <div v-if="organizers.length" class="relative">
        <div
            ref="scrollContainer"
            class="flex gap-6 overflow-x-auto scrollbar-hide"
            style="scroll-snap-type: x mandatory;"
        >
            <a
                v-for="organizer in organizers"
                :key="organizer.id"
                :href="`/organizers/${organizer.slug}`"
                class="flex-shrink-0 w-44 snap-start snap-always group"
            >
                <div class="w-44 aspect-square rounded-full overflow-hidden bg-neutral-100 mb-3 group-hover:opacity-90 transition-opacity">
                    <template v-if="organizer.thumbImagePath">
                        <picture>
                            <source type="image/webp" :srcset="`${imageUrl}${organizer.thumbImagePath}`">
                            <img
                                class="w-full h-full object-cover"
                                :src="`${imageUrl}${organizer.thumbImagePath.slice(0, -4)}jpg`"
                                :alt="organizer.name"
                            >
                        </picture>
                    </template>
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <span class="text-4xl font-bold text-neutral-400">{{ organizer.name?.[0]?.toUpperCase() }}</span>
                    </div>
                </div>
                <p class="font-semibold line-clamp-2">{{ organizer.name }}</p>
            </a>
        </div>

        <button
            v-if="canScrollLeft"
            type="button"
            class="hidden md:flex absolute -left-4 top-[38%] -translate-y-1/2 w-10 h-10 rounded-full bg-white shadow-custom-1 items-center justify-center hover:scale-105 transition-transform"
            @click="scroll(-1)"
        >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </button>
        <button
            v-if="canScrollRight"
            type="button"
            class="hidden md:flex absolute -right-4 top-[38%] -translate-y-1/2 w-10 h-10 rounded-full bg-white shadow-custom-1 items-center justify-center hover:scale-105 transition-transform"
            @click="scroll(1)"
        >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 18l6-6-6-6"/>
            </svg>
        </button>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue';

const props = defineProps({
    organizers: {
        type: Array,
        default: () => [],
    },
});

const imageUrl = import.meta.env.VITE_IMAGE_URL;
const scrollContainer = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

// Real overflow/scroll-position state (organizers.length alone can't tell —
// how many cards fit depends on viewport width) — a 1px tolerance absorbs
// sub-pixel rounding so the last card's arrow doesn't stay stuck visible.
const updateScrollState = () => {
    const el = scrollContainer.value;
    if (!el) return;
    canScrollLeft.value = el.scrollLeft > 1;
    canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 1;
};

const scroll = (direction) => {
    if (!scrollContainer.value) return;
    scrollContainer.value.scrollBy({ left: direction * scrollContainer.value.offsetWidth, behavior: 'smooth' });
};

onMounted(() => {
    updateScrollState();
    scrollContainer.value?.addEventListener('scroll', updateScrollState);
    window.addEventListener('resize', updateScrollState);
});

onUnmounted(() => {
    scrollContainer.value?.removeEventListener('scroll', updateScrollState);
    window.removeEventListener('resize', updateScrollState);
});

// The carousel can render after the list first arrives (async fetch), so
// re-measure once the newly-rendered cards are actually in the DOM.
watch(() => props.organizers, () => nextTick(updateScrollState));
</script>
