<template>
    <!-- Duplicate, asked in Airbnb's pop-up (not the browser's box). Duplicating
         is the point; the show run is a small checkbox under it:
           - in a show run: "Add the copy to {run}" (ticked);
           - live, in none: "Also start a show run" (not ticked);
           - otherwise no checkbox at all. -->
    <Teleport to="body">
        <div class="fixed inset-0 bg-black/50 flex items-end md:items-center justify-center z-[1003]" @click="close">
            <div class="bg-white w-full md:w-[50rem] md:mx-4 rounded-t-[3.2rem] md:rounded-[3.2rem] shadow-2xl relative max-h-[90vh] overflow-y-auto px-[2.4rem] pt-[2.4rem] pb-[3.2rem]" role="dialog" aria-modal="true" aria-labelledby="duplicate-ask-title" data-duplicate-ask @click.stop>
                <button type="button" aria-label="Close" class="w-[4rem] h-[4rem] -ml-[0.8rem] rounded-full flex items-center justify-center hover:bg-[#f7f7f7]" @click="close">
                    <svg class="w-[1.8rem] h-[1.8rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                </button>

                <div class="flex flex-col items-center text-center mt-2">
                    <div class="w-[18.5rem] aspect-square rounded-[1.6rem] overflow-hidden bg-[#ebebeb]">
                        <img v-if="picture" :src="picture" :alt="event.name || ''" class="w-full h-full object-cover">
                    </div>
                    <h2 id="duplicate-ask-title" class="mt-[2rem] text-[1.8rem] leading-[2.4rem] font-semibold text-[#222222] break-words hyphens-auto">Duplicate {{ event.name || 'this event' }}?</h2>
                    <p class="mt-1 text-[1.5rem] text-[#6a6a6a] font-normal">Makes a copy with all its details.</p>
                </div>

                <RunSwitch v-if="runOffer" v-model="asRun" :offer="runOffer" class="mt-[2.4rem]" />

                <p v-if="error" class="mt-4 text-[1.4rem] text-[#c13515] text-center" role="alert">{{ error }}</p>

                <button type="button" :disabled="busy" class="mt-[2.4rem] w-full h-[5.2rem] rounded-[1.2rem] bg-[#222222] hover:bg-black text-white text-[1.6rem] font-semibold disabled:opacity-50" data-duplicate-yes @click="duplicate">
                    {{ busy ? 'Making a copy…' : 'Duplicate' }}
                </button>
                <button type="button" :disabled="busy" class="mt-[1.6rem] w-full h-[4.8rem] rounded-[1.2rem] text-[1.6rem] font-semibold text-[#222222] hover:bg-[#f7f7f7] disabled:opacity-50" @click="close">Cancel</button>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import RunSwitch from './run-switch.vue';
import { runOfferFor } from './runOffer';

const props = defineProps({
    event: { type: Object, required: true },
});
const emit = defineEmits(['close']);

const imageUrl = import.meta.env.VITE_IMAGE_URL;
const picture = computed(() => {
    const path = props.event.images?.[0]?.large_image_path ?? props.event.thumbImagePath ?? null;
    return path ? `${imageUrl}${path}` : null;
});

// The small show run switch (runOffer.js).
const runOffer = computed(() => runOfferFor(props.event));
const asRun = ref(Boolean(runOffer.value?.on));

const busy = ref(false);
const error = ref('');
const close = () => {
    if (!busy.value) emit('close');
};
const failure = (error_, fallback) => error_.response?.status === 429 ? 'Too many tries just now. Please wait a minute and try again.' : Object.values(error_.response?.data?.errors ?? {}).flat()[0] ?? error_.response?.data?.message ?? fallback;
const duplicate = async () => {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    let copy;
    try {
        const response = await axios.post(`/api/events/${props.event.slug}/duplicate`, { as_run: Boolean(runOffer.value && asRun.value) });
        copy = response.data.event;
    } catch (failed) {
        busy.value = false;
        error.value = failure(failed, 'Could not make a copy. Please try again.');
        return;
    }
    // Always the copy itself, to set its new dates; a show run it started is already made.
    window.location.href = `/hosting/event/${copy.slug}/edit`;
};
</script>
