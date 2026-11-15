<template>
    <!-- "Is this your venue?" and a small Claim it button, as a company claims its
         organizer page. A person claims it; no organization needed. -->
    <div class="mt-[3.2rem] pt-[2.4rem] border-t border-[#dddddd] flex items-center justify-between gap-4">
        <p class="text-[1.4rem] leading-[1.8rem] font-medium text-[#222222]">Is this your venue?</p>

        <span v-if="sent || claim.pending" class="text-[1.2rem] leading-[1.6rem] text-[#6c6c6c]">Claim sent</span>
        <a v-else-if="!claim.signed_in" href="/login" class="flex-shrink-0 px-[1.6rem] py-[0.8rem] rounded-full border border-[#222222] text-[1.4rem] font-semibold text-[#222222] hover:bg-neutral-50">Claim it</a>
        <button v-else type="button" class="flex-shrink-0 px-[1.6rem] py-[0.8rem] rounded-full border border-[#222222] text-[1.4rem] font-semibold text-[#222222] hover:bg-neutral-50" @click="open = true">Claim it</button>

        <Teleport to="body">
            <div v-if="open" class="fixed inset-0 z-[500] flex items-end md:items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
                <div class="relative bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl p-10 space-y-8">
                    <div>
                        <h2 class="text-3xl font-semibold">Claim {{ claim.name }}</h2>
                        <p class="text-neutral-500 mt-2">We check every claim. Once approved, you can edit this venue's page.</p>
                    </div>
                    <label class="block">
                        <span class="font-medium">How are you connected to the venue? (optional)</span>
                        <textarea v-model="message" rows="4" maxlength="1000" class="mt-2 w-full p-4 border rounded-2xl border-[#222222]" placeholder="I run the box office; you can reach me at the venue's email."></textarea>
                    </label>
                    <p v-if="error" class="text-red-600">{{ error }}</p>
                    <div class="flex justify-end gap-4">
                        <button type="button" class="px-6 py-3 rounded-lg bg-gray-100 hover:bg-gray-200" @click="open = false">Cancel</button>
                        <button type="button" class="px-6 py-3 rounded-lg bg-black text-white hover:bg-gray-800 disabled:opacity-50" :disabled="busy" @click="send">
                            {{ busy ? 'Sending...' : 'Send claim' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    claim: { type: Object, required: true },
});

const open = ref(false);
const busy = ref(false);
const sent = ref(false);
const error = ref('');
const message = ref('');

const send = async () => {
    busy.value = true;
    error.value = '';
    try {
        await axios.post(`/api/venues/${props.claim.slug}/claim`, { message: message.value || null });
        sent.value = true;
        open.value = false;
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors ?? {}).flat()[0]
            ?? failure.response?.data?.message
            ?? 'That did not go through. Please try again.';
    } finally {
        busy.value = false;
    }
};
</script>
