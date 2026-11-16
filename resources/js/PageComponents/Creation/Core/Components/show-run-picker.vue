<template>
    <!-- Which show run this draft joins, beside Back in the bottom bar: the
         show's picture and name, and a menu to pick another or none. A draft
         only: a live listing is linked by EI staff. -->
    <div v-if="visible" ref="root" class="relative min-w-0">
        <button
            type="button"
            class="flex items-center gap-3 max-w-full pl-[0.5rem] pr-[1.3rem] h-[4.6rem] rounded-full border transition-colors"
            :class="open ? 'border-[#222222] bg-[#f7f7f7]' : 'border-[#dddddd] hover:border-[#222222]'"
            :aria-expanded="open"
            aria-haspopup="listbox"
            :disabled="busy"
            @click="toggle"
        >
            <span class="w-[3.4rem] h-[3.4rem] rounded-full overflow-hidden bg-[#ebebeb] flex-shrink-0 flex items-center justify-center">
                <img v-if="current?.image" :src="`${imageUrl}${current.image}`" alt="" class="w-full h-full object-cover" />
                <svg v-else class="w-[1.8rem] h-[1.8rem] text-[#6a6a6a]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h10"/></svg>
            </span>
            <span class="min-w-0 text-left leading-tight">
                <span class="block text-[1.15rem] text-[#6a6a6a] font-normal">{{ current ? 'Part of' : 'Show run' }}</span>
                <span class="block text-[1.3rem] font-semibold text-[#222222] truncate max-w-[22rem]">{{ busy ? 'Saving…' : (current?.name ?? 'Not part of one') }}</span>
            </span>
            <svg class="w-[1.4rem] h-[1.4rem] flex-shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>

        <!-- Opens upward, over the form -->
        <div v-if="open" role="listbox" class="absolute bottom-full left-0 mb-3 w-[32rem] max-w-[calc(100vw-3.2rem)] max-h-[40rem] overflow-y-auto rounded-[1.6rem] bg-white shadow-[0_8px_28px_rgba(0,0,0,0.28)] py-2 z-[1100]">
            <p class="px-6 pt-3 pb-2 text-[1.2rem] font-semibold text-[#6a6a6a] uppercase tracking-wide">Part of a show run</p>
            <button
                v-for="choice in choices"
                :key="choice.id"
                type="button"
                role="option"
                :aria-selected="current?.id === choice.id"
                class="w-full flex items-center gap-4 px-6 py-3 text-left hover:bg-[#f7f7f7]"
                @click="pick(choice)"
            >
                <span class="w-[4rem] h-[4rem] rounded-xl overflow-hidden bg-[#ebebeb] flex-shrink-0">
                    <img v-if="choice.image" :src="`${imageUrl}${choice.image}`" alt="" class="w-full h-full object-cover" />
                </span>
                <span class="flex-1 min-w-0 text-[1.35rem] font-medium text-[#222222] truncate">{{ choice.name }}</span>
                <svg v-if="current?.id === choice.id" class="w-[1.8rem] h-[1.8rem] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </button>
            <div class="my-2 border-t border-[#ebebeb]"></div>
            <button type="button" role="option" :aria-selected="!current" class="w-full flex items-center gap-4 px-6 py-3 text-left hover:bg-[#f7f7f7]" @click="pick(null)">
                <span class="flex-1 text-[1.35rem] font-medium text-[#222222]">Not part of a show run</span>
                <svg v-if="!current" class="w-[1.8rem] h-[1.8rem] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </button>
            <p class="px-6 pt-2 pb-3 text-[1.2rem] leading-[1.6rem] text-[#6a6a6a]">Switching keeps what was already filled in from the show.</p>
            <p v-if="error" class="px-6 py-2 text-[1.3rem] text-red-600">{{ error }}</p>
        </div>
        <p v-if="note" class="absolute bottom-full left-0 mb-3 w-[32rem] max-w-[calc(100vw-3.2rem)] px-5 py-3 rounded-2xl bg-[#222222] text-white text-[1.3rem]" role="status">{{ note }} <button type="button" class="underline ml-1" @click="note = ''">OK</button></p>
    </div>
</template>

<script setup>
import { computed, inject, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const event = inject('event');
const saveCurrentStep = inject('saveCurrentStep', null);
const imageUrl = import.meta.env.VITE_IMAGE_URL;

const root = ref(null);
const choices = ref([]);
const open = ref(false);
const busy = ref(false);
const error = ref('');
const note = ref('');

const isDraft = computed(() => !['r', 'p', 'e'].includes(event.status) && !event.project_id);

// What the draft is asking to join right now (a show run; joining a standalone
// listing shows its name with no choice ticked).
const current = computed(() => {
    const request = event.link_request;
    if (!request) return null;
    const choice = choices.value.find((item) => item.id === request.project_id);
    return { id: request.project_id ?? null, name: request.show, image: request.image ?? choice?.image ?? null };
});

// Shown when the draft is joining one, or its company has any to join.
const visible = computed(() => isDraft.value && (event.link_request || choices.value.length));

const load = async () => {
    if (!isDraft.value) return;
    try {
        const response = await axios.get(`/hosting/event/${event.slug}/show-runs`);
        choices.value = response.data.choices ?? [];
    } catch {
        choices.value = [];
    }
};

const toggle = () => {
    error.value = '';
    open.value = !open.value;
};

const pick = async (choice) => {
    if ((choice?.id ?? null) === (current.value?.id ?? null) && (choice || !event.link_request)) {
        open.value = false;
        return;
    }
    busy.value = true;
    error.value = '';
    // The one being left stays in the list, so switching back finds it.
    const leaving = current.value;
    if (leaving?.id && !choices.value.some((item) => item.id === leaving.id)) {
        choices.value = [...choices.value, { id: leaving.id, name: leaving.name, image: leaving.image }];
    }
    try {
        if (choice) {
            // Save what is typed on this step first: joining fills empty fields on
            // the server and the page reloads to show them, so nothing typed is lost.
            const saved = saveCurrentStep ? await saveCurrentStep() : 'saved';
            const response = await axios.post(`/hosting/event/${event.slug}/link-request`, { project_id: choice.id });
            event.link_request = { id: response.data.link_request.id, show: choice.name, project_id: choice.id, image: choice.image, details_confirmed: false };
            if (response.data.filled?.length) {
                if (saved === 'saved') {
                    window.location.reload();
                    return;
                }
                // This step could not be saved: keep it on screen rather than reload over it.
                note.value = 'Joined. Some details were filled in from the show; you will see them once this step is saved.';
            }
        } else {
            await axios.delete(`/hosting/event/${event.slug}/link-request`);
            event.link_request = null;
        }
        open.value = false;
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors ?? {}).flat()[0]
            ?? failure.response?.data?.message
            ?? 'That did not work. Please try again.';
    } finally {
        busy.value = false;
    }
};

const onOutside = (e) => {
    if (open.value && root.value && !root.value.contains(e.target)) open.value = false;
};
const onKey = (e) => {
    if (e.key === 'Escape') open.value = false;
};

onMounted(() => {
    load();
    document.addEventListener('mousedown', onOutside);
    document.addEventListener('keydown', onKey);
});
onUnmounted(() => {
    document.removeEventListener('mousedown', onOutside);
    document.removeEventListener('keydown', onKey);
});
</script>
