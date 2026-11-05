<template>
    <div v-if="event.link_request" class="w-full md:w-[61rem] mx-auto mb-8 p-8 rounded-3xl border border-neutral-300" data-confirm-details>
        <p class="text-xl font-semibold mb-2">This run joins {{ event.link_request.show }}</p>
        <p class="text-gray-600 mb-4">Each run sets its own details. Please check these are right for this one.</p>
        <label class="flex items-start gap-4 text-xl">
            <input type="checkbox" class="mt-2" :checked="event.link_request.details_confirmed" :disabled="event.link_request.details_confirmed || busy" @change="confirm">
            <span>The warnings, age range, accessibility, address and tickets are right for this run.</span>
        </label>
        <p v-if="error" class="text-red-500 mt-2">{{ error }}</p>
    </div>
</template>

<script setup>
import { inject, ref } from 'vue';
import axios from 'axios';

const event = inject('event');
const busy = ref(false);
const error = ref('');

// The Review step's Submit asks this; a refusal stays on the page.
const isConfirmed = () => {
    const confirmed = !event.link_request || event.link_request.details_confirmed;
    error.value = confirmed ? '' : 'Please confirm these details before submitting.';
    return confirmed;
};

defineExpose({ isConfirmed });

const confirm = async () => {
    busy.value = true;
    error.value = '';
    try {
        await axios.post(`/hosting/event/${event.slug}/link-request/confirm`);
        event.link_request.details_confirmed = true;
    } catch {
        error.value = 'That did not save. Please try again.';
    } finally {
        busy.value = false;
    }
};
</script>
