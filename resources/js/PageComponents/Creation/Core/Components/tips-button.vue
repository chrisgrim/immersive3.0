<template>
    <!-- The Tips pill and its pop-up (Airbnb style: close on the left, the tips, Got it).
         The page provides `helperTips`; each helper-tip on screen lands in the pop-up's body,
         which is always in the page (hidden while closed) so the tips have somewhere to go. -->
    <button
        type="button"
        aria-haspopup="dialog"
        class="inline-flex items-center gap-[0.6rem] h-[3.6rem] md:h-[4rem] px-[1.1rem] md:px-[1.5rem] rounded-full border border-[#dddddd] hover:border-[#222222] bg-white text-[1.3rem] md:text-[1.4rem] font-semibold text-[#222222] transition-colors"
        :class="{ 'tips-shake': shake }"
        aria-label="Tips"
        data-tips-toggle
        @click="open"
    >
        <svg class="w-[1.8rem] h-[1.8rem]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3a6 6 0 00-3.6 10.8c.7.5 1.1 1.3 1.1 2.2h5c0-.9.4-1.7 1.1-2.2A6 6 0 0012 3z" />
            <path stroke-linecap="round" d="M9.5 18.5h5M10.5 21h3" fill="none" />
        </svg>
        <span class="hidden sm:inline">Tips</span>
    </button>

    <Teleport to="body">
        <div
            v-show="tips.open"
            class="fixed inset-0 bg-black/50 flex items-end md:items-center justify-center z-[1003]"
            role="dialog"
            aria-modal="true"
            aria-labelledby="helper-tips-title"
            data-tips-modal
            @click="tips.open = false"
            @keydown.esc="tips.open = false"
        >
            <div ref="dialog" tabindex="-1" class="bg-white w-full md:w-[52rem] md:mx-4 rounded-t-[3.2rem] md:rounded-[3.2rem] shadow-2xl max-h-[88dvh] flex flex-col focus:outline-none" @click.stop>
                <div class="relative flex items-center justify-center h-[6.4rem] px-[2.4rem] border-b border-[#ebebeb] flex-shrink-0">
                    <button type="button" aria-label="Close" class="absolute left-[1.6rem] w-[4rem] h-[4rem] rounded-full flex items-center justify-center hover:bg-[#f7f7f7]" @click="tips.open = false">
                        <svg class="w-[1.6rem] h-[1.6rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                    <h2 id="helper-tips-title" class="text-[1.6rem] font-semibold text-[#222222]">Tips</h2>
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-[2.4rem] md:px-[3.2rem] pt-[3.2rem] pb-[3.6rem]">
                    <div id="helper-tips-body"></div>
                    <p v-if="!tips.count" class="text-[1.6rem] leading-[2.4rem] text-[#6a6a6a]" data-tips-none>
                        Nothing tricky on this step. Stuck anyway? Email us with the Questions? button.
                    </p>
                </div>
                <div class="flex justify-end px-[2.4rem] md:px-[3.2rem] py-[1.6rem] border-t border-[#ebebeb] flex-shrink-0">
                    <button type="button" class="h-[4.8rem] px-[2.4rem] rounded-[0.8rem] bg-[#222222] hover:bg-black text-white text-[1.6rem] font-semibold" @click="tips.open = false">
                        Got it
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { inject, nextTick, ref } from 'vue';

defineProps({
    // A little wiggle, twice, to point a newcomer at the tips.
    shake: { type: Boolean, default: false },
});
const emit = defineEmits(['open']);

const tips = inject('helperTips');
const dialog = ref(null);

const open = async () => {
    emit('open');
    tips.open = true;
    await nextTick();
    dialog.value?.focus();
};
</script>

<style scoped>
.tips-shake { animation: tips-shake 4s ease-in-out 0.8s 1 both; }
@keyframes tips-shake {
    0%, 15%, 50%, 65%, 100% { transform: rotate(0); }
    3%, 53% { transform: rotate(-8deg); }
    6%, 56% { transform: rotate(8deg); }
    9%, 59% { transform: rotate(-6deg); }
    12%, 62% { transform: rotate(4deg); }
}
@media (prefers-reduced-motion: reduce) { .tips-shake { animation: none; } }
</style>
